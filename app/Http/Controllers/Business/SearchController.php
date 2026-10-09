<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\SearchNearbyBusinessesRequest;
use App\Http\Resources\BusinessSearchResource;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    /**
     * Search public listings within the requested area.
     */
    public function __invoke(SearchNearbyBusinessesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->businessRepo->searchByRadius((float) $validated['lat'], (float) $validated['lng'], (float) $validated['radius'], $validated['keyword'] ?? null, $validated['category'] ?? null);

        return response()->json(['status' => 'success', 'data' => BusinessSearchResource::collection($results)->resolve($request)]);
    }
}
