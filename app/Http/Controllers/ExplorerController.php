<?php

namespace App\Http\Controllers;

use App\Http\Requests\Explorer\IndexExplorerRequest;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Services\ExplorerService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ExplorerController extends Controller
{
    public function __construct(
        private readonly ExplorerService $explorerService,
        private readonly PlaceCollectionRepositoryInterface $collectionRepo,
    ) {}

    /**
     * Display the explorer for guests and signed-in users.
     */
    public function __invoke(IndexExplorerRequest $request): Response
    {
        $collection = $request->filled('collection')
            ? $this->collectionRepo->findPublicByTokenOrFail($request->validated('collection'))
            : null;

        if ($collection) {
            Gate::authorize('viewPublic', $collection);
        }

        return Inertia::render('explorer/Index', $this->explorerService->pageData(
            $request->validated('category', 'all'),
            $request->user(),
            $request->validated('destination'),
            $collection,
            $request->integer('collection_page', 1),
            $request->safe()->only(['listings_query', 'listings_page']),
        ))->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
