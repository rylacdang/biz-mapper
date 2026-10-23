<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CollectionPageRequest;
use App\Http\Requests\Collection\SaveCollectionRequest;
use App\Http\Resources\PlaceCollectionResource;
use App\Http\Resources\SavedBusinessResource;
use App\Models\PlaceCollection;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionController extends Controller
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo
    ) {}

    /**
     * List only the current consumer's private collections.
     */
    public function index(CollectionPageRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', PlaceCollection::class);

        return PlaceCollectionResource::collection(
            $this->collectionRepo->forUser($request->user()->id, $request->integer('page', 1))
        )->response()->header('Cache-Control', 'private, no-store');
    }

    /**
     * Create a private collection for the authenticated consumer.
     */
    public function store(SaveCollectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', PlaceCollection::class);

        $this->collectionRepo->create([...$request->validated(), 'user_id' => $request->user()->id]);

        return back();
    }

    /**
     * Paginate the available places in an owned collection.
     */
    public function show(CollectionPageRequest $request, PlaceCollection $collection): JsonResponse
    {
        Gate::authorize('view', $collection);

        $places = $this->collectionRepo->places($collection, $request->integer('page', 1));
        $collection->setAttribute('places_count', $places->total());

        return SavedBusinessResource::collection($places)->additional(['collection' => new PlaceCollectionResource($collection)])
            ->response()->header('Cache-Control', 'private, no-store');
    }

    /**
     * Rename a collection without changing its ownership.
     */
    public function update(SaveCollectionRequest $request, PlaceCollection $collection): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $this->collectionRepo->update($collection->id, $request->validated());

        return back();
    }

    /**
     * Delete the collection while keeping the consumer's saved places.
     */
    public function destroy(PlaceCollection $collection): RedirectResponse
    {
        Gate::authorize('delete', $collection);

        $this->collectionRepo->delete($collection->id);

        return back();
    }
}
