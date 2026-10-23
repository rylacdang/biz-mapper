<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\CollectionPageRequest;
use App\Http\Resources\SavedBusinessResource;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionPlaceController extends Controller
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo
    ) {}

    /**
     * Offer only the consumer's own available saves in the picker.
     */
    public function index(CollectionPageRequest $request, PlaceCollection $collection): JsonResponse
    {
        Gate::authorize('view', $collection);

        return SavedBusinessResource::collection(
            $this->collectionRepo->availablePlaces($collection, $request->integer('page', 1))
        )->response()->header('Cache-Control', 'private, no-store');
    }

    /**
     * Add an owned save to an owned collection.
     */
    public function store(PlaceCollection $collection, SavedBusiness $savedBusiness): RedirectResponse
    {
        Gate::authorize('managePlace', [$collection, $savedBusiness]);

        $this->collectionRepo->addPlace($collection->id, $savedBusiness->id);

        return back();
    }

    /**
     * Remove membership while retaining the saved place.
     */
    public function destroy(PlaceCollection $collection, SavedBusiness $savedBusiness): RedirectResponse
    {
        Gate::authorize('managePlace', [$collection, $savedBusiness]);

        $this->collectionRepo->removePlace($collection->id, $savedBusiness->id);

        return back();
    }
}
