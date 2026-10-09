<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\ShowBusinessRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Services\BusinessService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController extends Controller
{
    public function __construct(
        private readonly BusinessService $businessService
    ) {}

    /**
     * Display a shareable business profile.
     */
    public function __invoke(ShowBusinessRequest $request): Response
    {
        $business = $this->businessService->getBusinessProfile($request->validated('slug'));

        Gate::authorize('view', $business);

        return Inertia::render('businesses/Show', [
            'business' => (new BusinessProfileResource($business))->resolve($request),
            'metadata' => $this->businessService->profileMetadata($business),
        ]);
    }
}
