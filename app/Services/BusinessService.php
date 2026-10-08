<?php

namespace App\Services;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BusinessService
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    /**
     * Handle business registration logic
     */
    public function registerBusiness(array $data, int $userId, UploadedFile $coverPhoto, ?UploadedFile $logo): Business
    {
        $data['user_id'] = $userId;
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        // Handle file uploads
        $data['cover_photo'] = $coverPhoto->store('covers', 'public');

        if ($logo) {
            $data['logo'] = $logo->store('logos', 'public');
        }

        return $this->businessRepo->create($data);
    }

    /**
     * Handle business update logic — replaces files only when new ones are uploaded.
     */
    public function updateBusiness(Business $business, array $data, ?UploadedFile $coverPhoto, ?UploadedFile $logo): bool
    {
        // Prevent null from validated() overwriting existing file paths
        unset($data['cover_photo'], $data['logo']);

        if ($coverPhoto) {
            Storage::disk('public')->delete($business->cover_photo);
            $data['cover_photo'] = $coverPhoto->store('covers', 'public');
        }

        if ($logo) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $data['logo'] = $logo->store('logos', 'public');
        }

        // Regenerate slug only if name changed
        if (isset($data['name']) && $data['name'] !== $business->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        }

        return $this->businessRepo->update($business->id, $data);
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
