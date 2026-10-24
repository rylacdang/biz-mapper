<?php

use App\Ai\Agents\BusinessReviewSummary;
use App\Enums\ReviewStatus;
use App\Enums\ReviewSummaryStatus as Status;
use App\Enums\UserRole;
use App\Events\ReviewSummaryInvalidated;
use App\Jobs\GenerateBusinessSummary;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewSummary;
use App\Models\User;
use App\Services\ReviewSummaryService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    Queue::fake();
    config(['ai.providers.groq.key' => 'test-only', 'ai.review_summaries.enabled' => true]);
    BusinessReviewSummary::fake()->preventStrayPrompts();
    $this->freezeTime();
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'Summary Cafe', 'slug' => 'summary-cafe', 'description' => 'A nearby cafe.',
        'cover_photo' => 'covers/sample.jpg', 'address' => 'Sample Street', 'lat' => 14.6, 'lng' => 121.0, 'hours' => [],
    ]);
    $this->service = app(ReviewSummaryService::class);
    $this->output = [
        'summary' => 'Reviewers appreciate the friendly service and coffee. Some mention longer waits during busy visits.',
        'sources' => ['R1', 'R2'],
    ];
});

function summaryReviews(Business $business, int $count = 3, string $body = 'Masarap ang coffee, friendly staff pero medyo matagal.'): void
{
    for ($i = 0; $i < $count; $i++) {
        Review::create([
            'business_id' => $business->id, 'user_id' => User::factory()->create(['role' => UserRole::CONSUMER])->id,
            'rating' => 4, 'body' => $body, 'status' => ReviewStatus::PUBLISHED,
        ]);
    }
}

function generateSummary(ReviewSummaryService $service, Business $business): void
{
    test()->travel(61)->seconds();
    $service->generate($business->id, $business->reviewSummary()->firstOrFail()->revision);
}

test('three published reviews are required and hidden reviews never count', function () {
    summaryReviews($this->business, 2);
    $review = Review::first();
    $review->update(['status' => ReviewStatus::HIDDEN]);
    $this->getJson(route('businesses.review-summary.show', $this->business->slug))
        ->assertOk()->assertJsonPath('data.status', 'insufficient_reviews')
        ->assertJsonPath('data.review_count', 1)->assertJsonPath('data.text', null);
    BusinessReviewSummary::assertNeverPrompted();
});

test('generation uses a bounded anonymous redacted mixed-language sample and explicit model', function () {
    config(['ai.default' => 'openai']);
    summaryReviews($this->business, 52, 'Masarap! Contact sample@example.test or +63 917 123 4567. '.str_repeat('a', 1400));
    Review::latest('id')->first()->update(['status' => ReviewStatus::HIDDEN]);
    ReviewReply::create(['review_id' => Review::first()->id, 'user_id' => $this->owner->id, 'body' => 'OWNER SECRET']);
    $captured = null;
    BusinessReviewSummary::fake(function (string $prompt) use (&$captured) {
        $captured = $prompt;

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    BusinessReviewSummary::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'openai/gpt-oss-120b' && $prompt->timeout === 30 && $prompt->provider->name() === 'groq');
    $reviews = json_decode($captured, true)['reviews'];
    expect($reviews)->toHaveCount(50)
        ->and(array_keys($reviews[0]))->toBe(['label', 'rating', 'body'])
        ->and(mb_strlen($reviews[0]['body']))->toBeLessThanOrEqual(1000)
        ->and($captured)->not->toContain('sample@example.test', '917 123', 'OWNER SECRET')
        ->and($captured)->toContain('Masarap', '[email removed]', '[phone removed]');
    $this->getJson(route('businesses.review-summary.show', $this->business->slug))
        ->assertJsonPath('data.status', 'ready')->assertJsonPath('data.review_count', 51)
        ->assertJsonPath('data.sampled_review_count', 50)->assertJsonMissingPath('data.source_fingerprint')
        ->assertJsonMissingPath('data.sources')->assertJsonPath('data.text', $this->output['summary']);
});

test('every review change restarts the full quiet period and superseded jobs exit', function () {
    summaryReviews($this->business);
    $old = $this->business->reviewSummary()->first();
    $this->travel(45)->seconds();
    Review::first()->update(['rating' => 2]);
    $current = $old->fresh();
    expect($current->revision)->toBe($old->revision + 1)
        ->and(abs($current->next_generation_at->diffInSeconds(now()->addSeconds(60))) < 1)->toBeTrue();
    $this->travel(16)->seconds();
    $this->service->generate($this->business->id, $old->revision);
    $this->service->generate($this->business->id, $current->revision);
    BusinessReviewSummary::assertNeverPrompted();
    BusinessReviewSummary::fake([$this->output])->preventStrayPrompts();
    $this->travel(45)->seconds();
    $this->service->generate($this->business->id, $current->revision);
    expect($current->fresh()->status)->toBe(Status::READY);
});

test('duplicate and overlapping executions never make a second provider request', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(function () {
        $state = $this->business->reviewSummary()->first();
        $this->service->generate($this->business->id, $state->revision);

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    $this->service->generate($this->business->id, $this->business->reviewSummary()->first()->revision);
    BusinessReviewSummary::assertPrompted(fn () => true);
    expect($this->business->reviewSummary()->first()->generation_attempts)->toBe(1);
    $job = new GenerateBusinessSummary($this->business->id, 1);
    expect($job->uniqueId())->toBe((string) $this->business->id)
        ->and($job->middleware())->toHaveCount(1);
});

test('a review change during the request discards the old response immediately', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(function () {
        Review::first()->update(['body' => 'My visit was different this time.']);

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    expect($this->business->reviewSummary()->first()->status)->toBe(Status::PENDING)
        ->and($this->business->reviewSummary()->first()->text)->toBeNull();
});

test('readonly freshness checks hide stale text without dispatching generation', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake([$this->output])->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    Queue::fake();
    Review::query()->whereKey(Review::first()->id)->update(['body' => 'An out of band change.']);
    $this->getJson(route('businesses.review-summary.show', $this->business->slug))
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.text', null);
    Queue::assertNothingPushed();
});

test('invalid structured output becomes unavailable', function (array $output) {
    summaryReviews($this->business);
    BusinessReviewSummary::fake([$output])->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    expect($this->business->reviewSummary()->first()->status)->toBe(Status::UNAVAILABLE)
        ->and($this->business->reviewSummary()->first()->text)->toBeNull();
})->with([
    [['summary' => '<b>Reviewers enjoy the coffee and friendly staff.</b>', 'sources' => ['R1', 'R2']]],
    [['summary' => 'Reviewers enjoy the coffee and friendly staff.', 'sources' => ['R1', 'R99']]],
    [['summary' => 'short', 'sources' => ['R1', 'R2']]],
    [['summary' => '*Reviewers appreciate the friendly staff and convenient location.*', 'sources' => ['R1', 'R2']]],
    [['summary' => '# Reviewers appreciate the friendly staff and convenient location.', 'sources' => ['R1', 'R2']]],
    [['summary' => str_repeat('a', 601), 'sources' => ['R1', 'R2']]],
    [['summary' => 'Reviewers enjoy the coffee and friendly staff.', 'sources' => ['R1', 'R1']]],
]);

test('missing credentials and permanent failures never break reviews', function () {
    summaryReviews($this->business);
    config(['ai.providers.groq.key' => null]);
    generateSummary($this->service, $this->business);
    BusinessReviewSummary::assertNeverPrompted();
    expect($this->business->reviewSummary()->first()->status)->toBe(Status::UNAVAILABLE);
    config(['ai.providers.groq.key' => 'test-only']);
    Review::first()->update(['rating' => 2]);
    BusinessReviewSummary::fake(fn () => throw new RuntimeException('private provider response'))->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    expect($this->business->reviewSummary()->first()->status)->toBe(Status::UNAVAILABLE)
        ->and(Review::count())->toBe(3);
});

test('connection failures stop after three bounded attempts', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(fn () => throw new ConnectionException('private'))->preventStrayPrompts();
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        expect(fn () => generateSummary($this->service, $this->business))->toThrow(RuntimeException::class, 'Temporary AI summary provider failure.');
    }
    expect($this->business->reviewSummary()->first()->status)->toBe(Status::UNAVAILABLE)
        ->and($this->business->reviewSummary()->first()->generation_attempts)->toBe(3);
});

test('owner replies do not invalidate summaries but publication and deletion do', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake([$this->output])->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    $state = $this->business->reviewSummary()->first();
    $review = Review::first();
    ReviewReply::create(['review_id' => $review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you!']);
    expect($state->fresh()->revision)->toBe($state->revision);
    $review->update(['status' => ReviewStatus::HIDDEN]);
    expect($state->fresh()->status)->toBe(Status::INSUFFICIENT_REVIEWS)->and($state->fresh()->text)->toBeNull();
    $review->delete();
    expect($state->fresh()->text)->toBeNull();
});

test('account cascades invalidate before source reviews disappear', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake([$this->output])->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    DB::transaction(fn () => Review::first()->author->delete());
    expect(Review::count())->toBe(2)
        ->and($this->business->reviewSummary()->first()->status)->toBe(Status::INSUFFICIENT_REVIEWS);
});

test('business deletion during generation rejects the response and restoration rechecks eligibility', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(function () {
        $this->business->delete();

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    $this->getJson(route('businesses.review-summary.show', $this->business->slug))->assertNotFound();
    expect(ReviewSummary::first()->text)->toBeNull();
    $this->business->restore();
    expect(ReviewSummary::first()->status)->toBe(Status::PENDING);
});

test('rolled back review changes roll back invalidation and do not enqueue', function () {
    summaryReviews($this->business);
    $state = ReviewSummary::first();
    Queue::fake();
    try {
        DB::transaction(function () {
            Review::first()->update(['rating' => 1]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect($state->fresh()->revision)->toBe($state->revision);
    Queue::assertNothingPushed();
});

test('scheduler backfills existing reviews and recovers expired workers without calling AI', function () {
    Review::withoutEvents(fn () => summaryReviews($this->business));
    $this->artisan('ai:summaries:dispatch')->assertSuccessful();
    $state = ReviewSummary::first();
    expect($state->status)->toBe(Status::PENDING)->and($state->next_generation_at->isFuture())->toBeTrue();
    $this->travel(61)->seconds();
    $this->artisan('ai:summaries:dispatch')->assertSuccessful();
    Queue::assertPushed(GenerateBusinessSummary::class);
    $state->update(['status' => Status::GENERATING, 'generation_started_at' => now()->subMinutes(3), 'generation_attempts' => 1]);
    $this->artisan('ai:summaries:dispatch')->assertSuccessful();
    expect($state->fresh()->status)->toBe(Status::PENDING);
    BusinessReviewSummary::assertNeverPrompted();
});

test('after commit dispatch waits for success and rollback discards the event', function () {
    Event::fake([ReviewSummaryInvalidated::class]);
    DB::transaction(function () {
        ReviewSummaryInvalidated::dispatch($this->business->id, 1);
        Event::assertNotDispatched(ReviewSummaryInvalidated::class);
    });
    Event::assertDispatchedTimes(ReviewSummaryInvalidated::class, 1);

    try {
        DB::transaction(function () {
            ReviewSummaryInvalidated::dispatch($this->business->id, 2);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Event::assertDispatchedTimes(ReviewSummaryInvalidated::class, 1);
});

test('queue uniqueness prevents concurrent jobs for one business', function () {
    $lock = new UniqueLock(Cache::store());
    $first = new GenerateBusinessSummary($this->business->id, 1);
    $second = new GenerateBusinessSummary($this->business->id, 2);

    try {
        expect($lock->acquire($first))->toBeTrue()
            ->and($lock->acquire($second))->toBeFalse();
    } finally {
        $lock->release($first);
    }
});

test('fingerprint guards reject writes that bypass review observers during generation', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(function () {
        Review::query()->whereKey(Review::first()->id)->update(['rating' => 1]);

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    expect(ReviewSummary::first()->status)->toBe(Status::PENDING)
        ->and(ReviewSummary::first()->text)->toBeNull();
});

test('provider availability is rechecked before publication', function () {
    summaryReviews($this->business);
    BusinessReviewSummary::fake(function () {
        config(['ai.review_summaries.enabled' => false]);

        return $this->output;
    })->preventStrayPrompts();
    generateSummary($this->service, $this->business);
    expect(ReviewSummary::first()->status)->toBe(Status::UNAVAILABLE)
        ->and(ReviewSummary::first()->text)->toBeNull();
});

test('a synchronous queue misconfiguration cannot run AI inside review submissions', function () {
    config(['ai.review_summaries.queue_connection' => 'sync']);

    expect((new GenerateBusinessSummary($this->business->id, 1))->connection)->toBe('database');
});
