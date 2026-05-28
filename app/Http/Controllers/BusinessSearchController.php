<?php

namespace App\Http\Controllers;

use App\Http\Requests\Business\SearchNearbyBusinessesRequest;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Http\JsonResponse;

class BusinessSearchController extends Controller
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepo
    ) {}

    public function search(SearchNearbyBusinessesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->businessRepo->searchByRadius(
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) $validated['radius'],
            $validated['keyword'] ?? null,
            $validated['category'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'data' => $results->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'description' => $business->description,
                    'address' => $business->address,
                    'phone' => $business->phone,
                    'website' => $business->website,
                    'cover_photo' => $business->cover_photo,
                    'logo' => $business->logo,
                    'lat' => $business->lat,
                    'lng' => $business->lng,
                    'distance' => round((float) $business->distance, 2), // distance in km
                    'category' => $business->category ? [
                        'id' => $business->category->id,
                        'name' => $business->category->name,
                        'color' => $business->category->color,
                        'icon' => $business->category->icon,
                    ] : null,
                ];
            }),
        ]);
    }
}
