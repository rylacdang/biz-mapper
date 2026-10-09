<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ShowBusinessRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService,
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    /**
     * Display a shareable business profile.
     */
    public function __invoke(ShowBusinessRequest $request): Response
    {
        $business = $this->businessRepo->findBySlugWithCategoryOrFail($request->validated('slug'));

        Gate::authorize('view', $business);

        return Inertia::render('businesses/Show', [
            'business' => (new BusinessProfileResource($business))->resolve($request),
            'metadata' => $this->businessService->profileMetadata($business),
        ]);
    }
}
