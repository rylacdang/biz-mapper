<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
    Cache::forget('walking-route-provider-slot');
    Http::preventStrayRequests();
    config(['services.routing.url' => 'https://routing.example/routed-foot']);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => User::factory()->create()->id,
        'category_id' => $category->id,
        'name' => 'Walking Coffee', 'slug' => 'walking-coffee',
        'description' => 'Coffee nearby.', 'address' => 'Sample Street',
        'lat' => 14.601, 'lng' => 121.001, 'hours' => [], 'cover_photo' => 'covers/sample.jpg',
    ]);
    $this->payload = [
        'code' => 'Ok',
        'routes' => [[
            'distance' => 650, 'duration' => 480,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[121.0, 14.6], [121.001, 14.601]]],
        ]],
    ];
    $this->url = route('businesses.directions', ['slug' => $this->business->slug, 'lat' => 14.6, 'lng' => 121.0]);
});

test('guests receive a normalized walking route and repeated requests use the cache', function () {
    Http::fake(['routing.example/*' => Http::response($this->payload)]);

    $this->getJson($this->url)->assertOk()
        ->assertJsonPath('distance', 650)
        ->assertJsonPath('duration', 480)
        ->assertJsonPath('geometry.type', 'LineString')
        ->assertJsonMissingPath('routes');
    $this->getJson($this->url)->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/routed-foot/route/v1/foot/121.000000,14.600000;121.001000,14.601000')
        && $request['geometries'] === 'geojson'
        && $request->hasHeader('User-Agent'));
});

test('directions validate the origin before contacting the provider', function () {
    $this->getJson(route('businesses.directions', ['slug' => $this->business->slug, 'lat' => 91, 'lng' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors(['lat', 'lng']);
    Http::assertNothingSent();
});

test('deleted destinations cannot receive directions', function () {
    $this->business->delete();
    $this->getJson($this->url)->assertNotFound();
    Http::assertNothingSent();
});

test('unroutable destinations return a helpful failure', function () {
    Http::fake(['routing.example/*' => Http::response(['code' => 'NoRoute'], 400)]);
    $this->getJson($this->url)->assertNotFound()->assertJsonPath('message', 'No walking route was found near these locations.');
});

test('provider connection failures remain recoverable', function () {
    Http::fake(['routing.example/*' => Http::failedConnection()]);
    $this->getJson($this->url)->assertStatus(503);
});

test('malformed provider geometry is rejected', function () {
    $this->payload['routes'][0]['geometry']['coordinates'] = [[999, 14.6], [121.001, 14.601]];
    Http::fake(['routing.example/*' => Http::response($this->payload)]);
    $this->getJson($this->url)->assertStatus(503);
});

test('outgoing directions respect the provider request limit', function () {
    Cache::put('walking-route-provider-slot', true, 10);
    $this->getJson($this->url)->assertStatus(429);
    Http::assertNothingSent();
});

test('public profile directions open the explorer with public destination data', function () {
    $this->get(route('explorer.index', ['destination' => $this->business->slug]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('explorer/Index')
        ->where('directionsBusiness.slug', $this->business->slug)
        ->missing('directionsBusiness.user_id'));
});
