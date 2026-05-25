<?php

namespace App\Services;

use App\Models\Business;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class BusinessService
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    /**
     * Handle business registration logic
     */
    public function registerBusiness(
        array $data,
        int $userId,
        UploadedFile $coverPhoto,
        ?UploadedFile $logo
    ): Business {
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
     * Generate unique URL slug
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = $this->businessRepo->getSlugPatternCount($slug);

        return $count ? "{$slug}-{$count}" : $slug;
    }
}
