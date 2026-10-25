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
    public function pageData(
        string $category,
        ?User $user,
        ?string $destination = null,
        ?PlaceCollection $collection = null,
        int $collectionPage = 1,
        array $listingFilters = [],
    ): array {
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
            $query = trim((string) ($listingFilters['listings_query'] ?? ''));
            $listings = $this->businessRepo->paginateByUserIdWithCategory(
                $user->id, $query, (int) ($listingFilters['listings_page'] ?? 1),
            );
            $data['myBusinesses'] = $listings->getCollection();
            $data['ownerPagination'] = [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'from' => $listings->firstItem(),
                'to' => $listings->lastItem(),
                'query' => $query,
            ];
            $data['ownerInsights'] = fn () => $this->businessRepo->getOwnerInsights($user->id, $listings->getCollection()->modelKeys())
                ->mapWithKeys(fn ($business) => [$business->id => [
                    'profile_views' => (int) $business->view_count,
                    'review_count' => (int) $business->published_reviews_count,
                    'average_rating' => $business->published_rating === null
                        ? null : round((float) $business->published_rating, 1),
                ]])->all();
            $data['trashBusinesses'] = fn () => $this->businessRepo->getTrashedByUserIdWithCategory($user->id);
        }

        return $data;
    }
}
