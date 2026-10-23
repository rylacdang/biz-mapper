<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CollectionPageRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Http\Resources\PublicCollectionResource;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Services\SavedBusinessService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShowController extends Controller
{
    public function __construct(
        private readonly PlaceCollectionRepositoryInterface $collectionRepo,
        private readonly SavedBusinessService $savedService,
    ) {}

    /**
     * Display only a currently published collection and its available businesses.
     */
    public function __invoke(CollectionPageRequest $request, string $shareToken): Response
    {
        $collection = $this->collectionRepo->findPublicByTokenOrFail($shareToken);

        Gate::authorize('viewPublic', $collection);

        $places = $this->collectionRepo->publicPlaces($collection, $request->integer('page', 1));
        $collection->setAttribute('places_count', $places->total());
        $this->savedService->markSaved($places->getCollection(), $request->user());

        return Inertia::render('collections/Show', [
            'collection' => (new PublicCollectionResource($collection))->resolve($request),
            'places' => BusinessProfileResource::collection($places),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
