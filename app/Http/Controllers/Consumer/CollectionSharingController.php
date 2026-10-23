<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\UpdateCollectionSharingRequest;
use App\Models\PlaceCollection;
use App\Services\CollectionSharingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CollectionSharingController extends Controller
{
    public function __construct(
        private readonly CollectionSharingService $sharingService
    ) {}

    /**
     * Let only the owner publish or unpublish a collection.
     */
    public function __invoke(UpdateCollectionSharingRequest $request, PlaceCollection $collection): RedirectResponse
    {
        Gate::authorize('update', $collection);

        $this->sharingService->update($collection, $request->boolean('is_public'));

        return back();
    }
}
