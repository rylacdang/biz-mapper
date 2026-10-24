<?php

namespace App\Services;

use App\Ai\Agents\BusinessReviewSummary;
use App\Enums\ReviewSummaryStatus as Status;
use App\Events\ReviewSummaryInvalidated;
use App\Jobs\GenerateBusinessSummary;
use App\Repositories\Contracts\ReviewSummaryRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;
use Throwable;

class ReviewSummaryService
{
    public function __construct(
        private readonly ReviewSummaryRepositoryInterface $summaryRepo,
        private readonly BusinessReviewSummary $agent,
    ) {}

    /**
     * Clear stale text in the same transaction as its source change.
     */
    public function invalidate(int $businessId, ?int $excludingUser = null): void
    {
        DB::transaction(function () use ($businessId, $excludingUser) {
            $business = $this->summaryRepo->lockBusiness($businessId);

            if (! $business) {
                return;
            }

            $source = $this->summaryRepo->snapshot($businessId, $excludingUser);
            $state = $this->summaryRepo->forBusiness($businessId);
            $eligible = ! $business->trashed() && $source['count'] >= 3;
            $attributes = [
                'business_id' => $businessId,
                'revision' => ($state?->revision ?? 0) + 1,
                'source_fingerprint' => $source['fingerprint'],
                'review_count' => $source['count'],
                'sampled_review_count' => count($source['reviews']),
                'status' => $eligible ? Status::PENDING : Status::INSUFFICIENT_REVIEWS,
                'text' => null,
                'generated_at' => null,
                'generation_started_at' => null,
                'generation_attempts' => 0,
                'next_generation_at' => $eligible ? now()->addSeconds(config('ai.review_summaries.debounce_seconds'))->ceilSecond() : null,
            ];
            $state
                ? $this->summaryRepo->update($state->id, $attributes)
                : $this->summaryRepo->create($attributes);

            if ($eligible) {
                ReviewSummaryInvalidated::dispatch($businessId, $attributes['revision']);
            }
        });
    }

    /**
     * Account cascades bypass individual review observers.
     */
    public function invalidateForUser(int $userId): void
    {
        foreach ($this->summaryRepo->businessIdsForUser($userId) as $businessId) {
            $this->invalidate($businessId, $userId);
        }
    }

    /**
     * Queue identifiers only; prompts are assembled inside the worker.
     */
    public function queue(int $businessId, int $revision): void
    {
        $state = $this->summaryRepo->forBusiness($businessId);

        if ($state && $state->revision === $revision && $state->status === Status::PENDING) {
            GenerateBusinessSummary::dispatch($businessId, $revision)
                ->delay($state->next_generation_at)->afterCommit();
        }
    }

    /**
     * Recover due work, existing businesses, and workers stopped mid-request.
     */
    public function dispatchDue(bool $retryUnavailable = false): void
    {
        foreach ($this->summaryRepo->missingBusinessIds() as $businessId) {
            $this->invalidate($businessId);
        }

        foreach ($this->summaryRepo->recoverableSummaries($retryUnavailable) as $candidate) {
            DB::transaction(function () use ($candidate) {
                $this->summaryRepo->lockBusiness($candidate->business_id);
                $state = $this->summaryRepo->forBusiness($candidate->business_id);

                if (! $state) {
                    return;
                }

                if ($state->status === Status::UNAVAILABLE) {
                    $this->invalidate($state->business_id);

                    return;
                }

                if ($state->status === Status::GENERATING) {
                    if ($state->generation_started_at?->gt(now()->subSeconds(120))) {
                        return;
                    }

                    $this->summaryRepo->update($state->id, [
                        'status' => $state->generation_attempts >= 3 ? Status::UNAVAILABLE : Status::PENDING,
                        'generation_started_at' => null,
                        'next_generation_at' => now(),
                    ]);
                }

                $this->queue($state->business_id, $state->revision);
            });
        }
    }

    /**
     * Read stored results without queuing or contacting a provider.
     */
    public function publicData(int $businessId): array
    {
        $source = $this->summaryRepo->snapshot($businessId);
        $state = $this->summaryRepo->forBusiness($businessId);
        $status = Status::INSUFFICIENT_REVIEWS;

        if ($source['count'] >= 3) {
            $status = $state?->source_fingerprint === $source['fingerprint']
                ? $state->status : Status::PENDING;

            if ($status !== Status::READY && ! $this->configured()) {
                $status = Status::UNAVAILABLE;
            }
        }

        return [
            'status' => $status->value,
            'text' => $status === Status::READY ? $state->text : null,
            'generated_at' => $status === Status::READY ? $state->generated_at?->toISOString() : null,
            'review_count' => $source['count'],
            'sampled_review_count' => count($source['reviews']),
        ];
    }

    /**
     * Perform network work outside locks and reject stale completions.
     */
    public function generate(int $businessId, int $revision): void
    {
        $source = $this->prepare($businessId, $revision);

        if ($source === null) {
            return;
        }

        if (! $this->configured()) {
            $this->recordFailure($businessId, $revision, false);

            return;
        }

        $started = microtime(true);

        try {
            $reviews = $this->promptReviews($source['reviews']);
            $response = $this->agent->prompt(
                json_encode(['reviews' => $reviews], JSON_THROW_ON_ERROR),
            );

            $data = Validator::make([
                'summary' => $response['summary'],
                'sources' => $response['sources'],
            ], [
                'summary' => ['required', 'string', 'min:40', 'max:600'],
                'sources' => ['required', 'array', 'min:2', 'max:50'],
                'sources.*' => ['required', 'string', 'distinct', Rule::in(array_column($reviews, 'label'))],
            ])->validate();

            $text = trim($data['summary']);

            if ($text !== strip_tags($text) || str_contains($text, chr(96))
                || preg_match('/[\r\n]|https?:|www\.|[*_~]|^\s*[#>\-]|\[[^\]]+\]\(/i', $text)) {
                throw new RuntimeException('Invalid summary format.');
            }
        } catch (Throwable $exception) {
            $transient = $this->transient($exception);
            $this->recordFailure($businessId, $revision, $transient);

            Log::warning('Review summary generation failed.', [
                'business_id' => $businessId, 'provider' => 'groq',
                'category' => class_basename($exception),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);

            if ($transient) {
                throw new RuntimeException('Temporary AI summary provider failure.');
            }

            return;
        }

        DB::transaction(function () use ($businessId, $revision, $source, $text, $started) {
            $business = $this->summaryRepo->lockBusiness($businessId);
            $state = $this->summaryRepo->forBusiness($businessId);

            if (! $business || $business->trashed() || ! $state || $state->revision !== $revision) {
                return;
            }

            if (! $this->configured()) {
                $this->recordFailure($businessId, $revision, false);

                return;
            }

            if ($this->summaryRepo->snapshot($businessId)['fingerprint'] !== $source['fingerprint']) {
                $this->invalidate($businessId);

                return;
            }

            $this->summaryRepo->update($state->id, [
                'status' => Status::READY, 'text' => $text, 'generated_at' => now(),
                'generation_started_at' => null, 'next_generation_at' => null,
            ]);
            Log::info('Review summary generated.', [
                'business_id' => $businessId, 'provider' => 'groq',
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        });
    }

    /**
     * Claim only the current due revision and bound recovery attempts.
     */
    private function prepare(int $businessId, int $revision): ?array
    {
        return DB::transaction(function () use ($businessId, $revision) {
            $business = $this->summaryRepo->lockBusiness($businessId);
            $state = $this->summaryRepo->forBusiness($businessId);

            if (! $business || $business->trashed() || ! $state || $state->revision !== $revision
                || $state->status !== Status::PENDING || $state->next_generation_at?->isFuture()) {
                return null;
            }

            $source = $this->summaryRepo->snapshot($businessId);

            if ($source['count'] < 3 || $source['fingerprint'] !== $state->source_fingerprint) {
                $this->invalidate($businessId);

                return null;
            }

            if ($state->generation_attempts >= 3) {
                $this->summaryRepo->update($state->id, ['status' => Status::UNAVAILABLE, 'next_generation_at' => null]);

                return null;
            }

            $this->summaryRepo->update($state->id, [
                'status' => Status::GENERATING, 'generation_started_at' => now(),
                'generation_attempts' => $state->generation_attempts + 1,
            ]);

            return $source;
        });
    }

    /**
     * Remove common contact details before truncating the outbound sample.
     */
    private function promptReviews(array $reviews): array
    {
        return array_map(function (array $review, int $index) {
            $body = strip_tags($review['body']);
            $body = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email removed]', $body);
            $body = preg_replace('/(?<!\w)\+?\d[\d\s().\-]{6,}\d(?!\w)/u', '[phone removed]', $body);

            return ['label' => 'R'.($index + 1), 'rating' => $review['rating'], 'body' => Str::limit($body, 1000, '')];
        }, $reviews, array_keys($reviews));
    }

    /**
     * A failure must not overwrite a newer invalidation.
     */
    private function recordFailure(int $businessId, int $revision, bool $transient): void
    {
        DB::transaction(function () use ($businessId, $revision, $transient) {
            $this->summaryRepo->lockBusiness($businessId);
            $state = $this->summaryRepo->forBusiness($businessId);

            if (! $state || $state->revision !== $revision) {
                return;
            }

            $retry = $transient && $state->generation_attempts < 3;
            $this->summaryRepo->update($state->id, [
                'status' => $retry ? Status::PENDING : Status::UNAVAILABLE,
                'generation_started_at' => null,
                'next_generation_at' => $retry ? now()->addSeconds(60) : null,
                'text' => null,
            ]);
        });
    }

    /**
     * Retry connection errors, overload, rate limits, and server errors only.
     */
    private function transient(Throwable $exception): bool
    {
        return $exception instanceof RateLimitedException
            || $exception instanceof ProviderOverloadedException
            || $exception instanceof ProviderConnectionException
            || $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
    }

    /**
     * Unconfigured AI must not affect ordinary review flows.
     */
    private function configured(): bool
    {
        return config('ai.review_summaries.enabled') && filled(config('ai.providers.groq.key'));
    }
}
