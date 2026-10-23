<?php

namespace App\Services;

use App\Enums\DiscoveryShortcut;
use App\Enums\UserRole;
use App\Http\Resources\BusinessProfileResource;
use App\Http\Resources\PublicCollectionResource;
use App\Models\PlaceCollection;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Laravel\Fortify\Features;

class ExplorerService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly BusinessRepositoryInterface $businessRepo,
        private readonly SavedBusinessService $savedService,
        private readonly PlaceCollectionRepositoryInterface $collectionRepo,
    ) {}

    /**
     * Include management records only for their owner.
     */
    public function pageData(string $category, ?User $user, ?string $destination = null, ?PlaceCollection $collection = null, int $collectionPage = 1): array
    {
        $data = [
            'canRegister' => Features::enabled(Features::registration()),
            'categories' => $this->categoryRepo->all(),
            'selectedCategory' => $category,
            'discoveryShortcuts' => array_map(fn (DiscoveryShortcut $shortcut) => [
                'id' => $shortcut->value,
                'label' => $shortcut->label(),
                'icon' => $shortcut->icon(),
                'categories' => $shortcut->categories(),
            ], DiscoveryShortcut::cases()),
        ];

        if ($collection) {
            $places = $this->collectionRepo->publicPlaces($collection, $collectionPage);
            $collection->setAttribute('places_count', $places->total());
            $this->savedService->markSaved($places->getCollection(), $user);
            $data['sharedCollection'] = (new PublicCollectionResource($collection))->resolve();
            $data['sharedPlaces'] = BusinessProfileResource::collection($places);
        }

        if ($destination) {
            $business = $this->businessRepo->findBySlugWithCategoryOrFail($destination);
            $this->savedService->markSaved($business, $user);
            $data['directionsBusiness'] = (new BusinessProfileResource($business))->resolve();
        }

        if ($user?->role === UserRole::OWNER) {
            $data['offeringCurrencies'] = config('business.currencies');
            $data['myBusinesses'] = $this->businessRepo->getByUserIdWithCategory($user->id);
            $data['trashBusinesses'] = fn () => $this->businessRepo->getTrashedByUserIdWithCategory($user->id);
        }

        return $data;
    }
}
