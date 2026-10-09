<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Http\Resources\BusinessProfileResource;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Laravel\Fortify\Features;

class ExplorerService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly BusinessRepositoryInterface $businessRepo,
    ) {}

    /**
     * Include management records only for their owner.
     */
    public function pageData(string $category, ?User $user, ?string $destination = null): array
    {
        $data = [
            'canRegister' => Features::enabled(Features::registration()),
            'categories' => $this->categoryRepo->all(),
            'allBusinesses' => BusinessProfileResource::collection(
                $this->businessRepo->getByCategoryWithCategory($category)
            )->resolve(),
            'selectedCategory' => $category,
        ];

        if ($destination) {
            $business = $this->businessRepo->findBySlugWithCategoryOrFail($destination);
            $data['directionsBusiness'] = (new BusinessProfileResource($business))->resolve();
        }

        if ($user?->role === UserRole::OWNER) {
            $data['myBusinesses'] = $this->businessRepo->getByUserIdWithCategory($user->id);
            $data['trashBusinesses'] = fn () => $this->businessRepo->getTrashedByUserIdWithCategory($user->id);
        }

        return $data;
    }
}
