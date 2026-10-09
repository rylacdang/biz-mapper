<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\GetDirectionsRequest;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\RoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DirectionsController extends Controller
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo,
        private readonly RoutingService $routingService,
    ) {}

    /**
     * Return a walking route to a public business.
     */
    public function __invoke(GetDirectionsRequest $request): JsonResponse
    {
        $business = $this->businessRepo->findBySlugWithCategoryOrFail($request->validated('slug'));

        Gate::authorize('view', $business);

        return response()->json($this->routingService->walkingRoute((float) $request->validated('lat'), (float) $request->validated('lng'), (float) $business->lat, (float) $business->lng));
    }
}
