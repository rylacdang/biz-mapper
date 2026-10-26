<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CollectionPageRequest;
use App\Http\Requests\Collection\MoveCollectionPlaceRequest;
use App\Http\Resources\PlaceCollectionResource;
use App\Http\Resources\SavedBusinessResource;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Services\CollectionOrderingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionPlaceController extends Controller
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo,
        private readonly CollectionOrderingService $ordering
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

        $this->ordering->add($collection, $savedBusiness->id);

        return back();
    }

    /**
     * Remove membership while retaining the saved place.
     */
    public function destroy(PlaceCollection $collection, SavedBusiness $savedBusiness): RedirectResponse
    {
        Gate::authorize('managePlace', [$collection, $savedBusiness]);

        $this->ordering->remove($collection, $savedBusiness->id);

        return back();
    }

    /**
     * Rearrange only a member owned by the current consumer.
     */
    public function update(MoveCollectionPlaceRequest $request, PlaceCollection $collection, SavedBusiness $savedBusiness): JsonResponse
    {
        Gate::authorize('managePlace', [$collection, $savedBusiness]);

        $this->ordering->move($collection, $savedBusiness->id, $request->validated('direction'));

        $places = $this->collectionRepo->places($collection, $request->integer('page', 1));
        $collection->setAttribute('places_count', $places->total());

        return SavedBusinessResource::collection($places)->additional(['collection' => new PlaceCollectionResource($collection)])
            ->response()->header('Cache-Control', 'private, no-store');
    }
}
