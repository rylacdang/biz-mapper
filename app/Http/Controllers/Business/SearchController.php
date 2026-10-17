<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\SearchNearbyBusinessesRequest;
use App\Http\Resources\BusinessMarkerResource;
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

        $arguments = [
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) $validated['radius'],
            $validated['keyword'] ?? null,
            $validated['category'] ?? null,
        ];
        $results = $this->businessRepo->searchByRadius(...[...$arguments, (int) ($validated['page'] ?? 1)]);

        $payload = [
            'status' => 'success',
            'data' => BusinessSearchResource::collection($results->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ];

        if ($request->boolean('include_markers', true)) {
            $payload['markers'] = BusinessMarkerResource::collection(
                $this->businessRepo->getNearbyMarkers(...$arguments)
            )->resolve($request);
        }

        return response()->json($payload);
    }
}
