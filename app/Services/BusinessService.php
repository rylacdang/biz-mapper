<?php

namespace App\Services;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BusinessService
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo,
        private readonly BusinessOfferingService $offeringService,
    ) {}

    /**
     * Count a visitor once per day without allowing analytics failures to block profiles.
     */
    public function recordProfileView(Business $business, ?int $viewerId, string $visitorKey): void
    {
        if ($viewerId === $business->user_id || $business->trashed()) {
            return;
        }

        $key = 'business-profile-view:'.$business->id.':'
            .hash_hmac('sha256', $visitorKey, config('app.key'));

        try {
            if (Cache::add($key, true, now()->addDay())) {
                $this->businessRepo->incrementProfileViews($business->id);
            }
        } catch (Throwable $exception) {
            Log::warning('Profile view tracking unavailable.', [
                'business_id' => $business->id,
                'category' => class_basename($exception),
            ]);
        }
    }

    /**
     * Build share metadata from the business's actual content.
     */
    public function profileMetadata(Business $business): array
    {
        return [
            'title' => $business->name.' | BizMapper',
            'description' => Str::limit(strip_tags($business->description), 160),
            'url' => route('businesses.show', $business->slug),
            'image' => $business->cover_photo
                ? url(Storage::disk('public')->url($business->cover_photo))
                : null,
        ];
    }

    /**
     * Handle business registration logic
     */
    public function registerBusiness(array $data, int $userId, UploadedFile $coverPhoto, ?UploadedFile $logo): Business
    {
        $uploads = [];

        try {
            $data['user_id'] = $userId;
            $data['slug'] = $this->generateUniqueSlug($data['name']);
            $data['gallery_photos'] = $this->storeGallery($data['gallery'] ?? [], $uploads);
            $offerings = $data['offerings'] ?? [];
            unset($data['gallery'], $data['replace_gallery'], $data['offerings']);

            $data['cover_photo'] = $coverPhoto->store('covers', 'public');
            $uploads[] = $data['cover_photo'];

            if ($logo) {
                $data['logo'] = $logo->store('logos', 'public');
                $uploads[] = $data['logo'];
            }

            // Registration and its draft catalog must succeed together.
            return DB::transaction(function () use ($data, $offerings, &$uploads) {
                $business = $this->businessRepo->create($data);

                foreach ($offerings as $draft) {
                    $item = $this->offeringService->create($business, $draft);

                    if ($item->photo) {
                        $uploads[] = $item->photo;
                    }
                }

                return $business;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($uploads);

            throw $exception;
        }
    }

    /**
     * Save listing changes before deleting replaced photos.
     */
    public function updateBusiness(Business $business, array $data, ?UploadedFile $coverPhoto, ?UploadedFile $logo): bool
    {
        $uploads = [];
        $removed = [];
        unset($data['cover_photo'], $data['logo']);

        try {
            if (array_key_exists('gallery', $data) || filter_var($data['replace_gallery'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $data['gallery_photos'] = $this->storeGallery($data['gallery'] ?? [], $uploads);
                $removed = array_diff($business->gallery_photos ?? [], $data['gallery_photos']);
            }

            unset($data['gallery'], $data['replace_gallery']);

            if ($coverPhoto) {
                $data['cover_photo'] = $coverPhoto->store('covers', 'public');
                $uploads[] = $data['cover_photo'];
                $removed[] = $business->cover_photo;
            }

            if ($logo) {
                $data['logo'] = $logo->store('logos', 'public');
                $uploads[] = $data['logo'];

                if ($business->logo) {
                    $removed[] = $business->logo;
                }
            }

            if (isset($data['name']) && $data['name'] !== $business->name) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }

            $updated = $this->businessRepo->update($business->id, $data);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($uploads);

            throw $exception;
        }

        Storage::disk('public')->delete($updated ? array_values($removed) : $uploads);

        return $updated;
    }

    /**
     * Keep the submitted photo order and track new files for failure cleanup.
     */
    private function storeGallery(array $entries, array &$uploads): array
    {
        $photos = [];

        foreach ($entries as $entry) {
            if (($entry['file'] ?? null) instanceof UploadedFile) {
                $path = $entry['file']->store('business-gallery', 'public');
                $uploads[] = $path;
                $photos[] = $path;
            } else {
                $photos[] = $entry['path'];
            }
        }

        return $photos;
    }

    /**
     * Generate unique URL slug
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = $this->businessRepo->getSlugPatternCount($slug);

        return $count ? "{$slug}-{$count}" : $slug;
    }
}
