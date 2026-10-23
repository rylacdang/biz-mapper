<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedBusiness\IndexSavedBusinessesRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Models\Business;
use App\Models\SavedBusiness;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedBusinessController extends Controller
{
    public function __construct(
        private readonly SavedBusinessRepositoryInterface $savedRepo,
    ) {}

    /**
     * Return only the authenticated consumer's saved places.
     */
    public function index(IndexSavedBusinessesRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', SavedBusiness::class);

        return BusinessProfileResource::collection(
            $this->savedRepo->forUser($request->user()->id, $request->integer('page', 1))
        )->response()->header('Cache-Control', 'private, no-store');
    }

    /**
     * Save an available business without accepting a client-supplied user ID.
     */
    public function store(Request $request, Business $business): RedirectResponse
    {
        Gate::authorize('create', [SavedBusiness::class, $business]);

        $this->savedRepo->saveForUser($request->user()->id, $business->id);

        return back();
    }

    /**
     * Unsave only the current consumer's record, including unavailable places.
     */
    public function destroy(Request $request, Business $business): RedirectResponse
    {
        Gate::authorize('delete', [SavedBusiness::class, $business]);

        $this->savedRepo->removeForUser($request->user()->id, $business->id);

        return back();
    }
}
