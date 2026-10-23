<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ShowBusinessRequest;
use App\Http\Resources\BusinessOfferingResource;
use App\Http\Resources\BusinessProfileResource;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessService;
use App\Services\SavedBusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService,
        private readonly BusinessRepositoryInterface $businessRepo,
        private readonly BusinessOfferingRepositoryInterface $offeringRepo,
        private readonly SavedBusinessService $savedService,
    ) {}

    /**
     * Display a shareable business profile.
     */
    public function __invoke(ShowBusinessRequest $request): Response|JsonResponse
    {
        $business = $this->businessRepo->findBySlugWithCategoryOrFail($request->validated('slug'));

        Gate::authorize('view', $business);
        $this->savedService->markSaved($business, $request->user());

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['data' => (new BusinessProfileResource($business))->resolve($request)])->header('Cache-Control', 'private, no-store');
        }

        return Inertia::render('businesses/Show', [
            'business' => (new BusinessProfileResource($business))->resolve($request),
            'offerings' => BusinessOfferingResource::collection($this->offeringRepo->forBusiness($business->id)),
            'metadata' => $this->businessService->profileMetadata($business),
        ]);
    }
}
