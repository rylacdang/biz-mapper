<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\ShowBusinessRequest;
use App\Http\Resources\BusinessOfferingResource;
use App\Http\Resources\BusinessProfileResource;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessService;
use App\Services\ReviewService;
use App\Services\SavedBusinessService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShowController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService,
        private readonly BusinessRepositoryInterface $businessRepo,
        private readonly BusinessOfferingRepositoryInterface $offeringRepo,
        private readonly SavedBusinessService $savedService,
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * Display a shareable business profile.
     */
    public function __invoke(ShowBusinessRequest $request): Response
    {
        $business = $this->businessRepo->findBySlugWithCategoryOrFail($request->validated('slug'));

        Gate::authorize('view', $business);
        $this->savedService->markSaved($business, $request->user());

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['data' => (new BusinessProfileResource($business))->resolve($request)])->header('Cache-Control', 'private, no-store');
        }

        $visitorKey = $request->profileVisitorKey();

        if ($visitorKey !== null) {
            $this->businessService->recordProfileView($business, $request->user()?->id, $visitorKey);
        }

        return Inertia::render('businesses/Show', [
            'business' => (new BusinessProfileResource($business))->resolve($request),
            'feedback' => $this->reviewService->pageData($business, $request->user()),
            'offerings' => BusinessOfferingResource::collection($this->offeringRepo->forBusiness($business->id)),
            'metadata' => $this->businessService->profileMetadata($business),
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
