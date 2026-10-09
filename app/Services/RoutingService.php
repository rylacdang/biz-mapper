<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RoutingService
{
    /**
     * Calculate walking directions using the configured foot-routing server.
     */
    public function walkingRoute(float $lat, float $lng, float $targetLat, float $targetLng): array
    {
        $coordinates = sprintf('%.6f,%.6f;%.6f,%.6f', $lng, $lat, $targetLng, $targetLat);
        $endpoint = rtrim(config('services.routing.url'), '/');
        $key = 'walking-route:'.hash('sha256', $endpoint.$coordinates);

        if ($route = Cache::get($key)) {
            return $route;
        }

        // The public routing service permits one outgoing request per second.
        abort_unless(Cache::add('walking-route-provider-slot', true, 1), 429,
            'Directions are busy. Please retry in a moment.');

        $route = $this->requestRoute($endpoint, $coordinates);
        Cache::put($key, $route, now()->addMinutes(10));

        return $route;
    }

    /**
     * Normalize the provider response without exposing its internal payload.
     */
    private function requestRoute(string $endpoint, string $coordinates): array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('app.name').' ('.config('app.url').')')
                ->connectTimeout(5)
                ->timeout(10)
                ->get($endpoint.'/route/v1/foot/'.$coordinates, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                    'radiuses' => '200;200',
                ]);
        } catch (ConnectionException) {
            abort(503, 'Walking directions are temporarily unavailable. Please retry.');
        }

        if ($response->json('code') === 'NoRoute' || $response->json('code') === 'NoSegment') {
            abort(404, 'No walking route was found near these locations.');
        }

        abort_unless($response->successful() && $response->json('code') === 'Ok', 503,
            'Walking directions are temporarily unavailable. Please retry.');

        $route = $response->json('routes.0');
        $points = data_get($route, 'geometry.coordinates');

        abort_unless(is_array($points) && count($points) >= 2
            && data_get($route, 'geometry.type') === 'LineString'
            && is_numeric(data_get($route, 'distance')) && $route['distance'] >= 0
            && is_numeric(data_get($route, 'duration')) && $route['duration'] >= 0,
            503, 'The routing service returned an invalid route. Please retry.');

        foreach ($points as $point) {
            abort_unless(is_array($point) && count($point) >= 2
                && is_numeric($point[0]) && is_numeric($point[1])
                && abs($point[0]) <= 180 && abs($point[1]) <= 90,
                503, 'The routing service returned an invalid route. Please retry.');
        }

        return [
            'geometry' => $route['geometry'],
            'distance' => (float) $route['distance'],
            'duration' => (float) $route['duration'],
        ];
    }
}
