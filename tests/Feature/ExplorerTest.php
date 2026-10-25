<?php

use App\Enums\DiscoveryShortcut;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // SQLite needs the math functions supplied by MySQL in production.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['acos' => 'acos', 'cos' => 'cos', 'sin' => 'sin', 'radians' => 'deg2rad'] as $name => $callback) {
            DB::connection()->getPdo()->sqliteCreateFunction($name, $callback, 1);
        }
    }

    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->listing = Business::create([
        'user_id' => $this->owner->id,
        'category_id' => $this->category->id,
        'name' => 'Explorer Coffee',
        'slug' => 'explorer-coffee',
        'description' => 'Coffee in the neighborhood.',
        'address' => 'Sample Street',
        'lat' => 14.6,
        'lng' => 121.0,
        'cover_photo' => 'covers/sample.jpg',
        'hours' => [],
    ]);
});

test('guests and consumers receive only public explorer records', function (bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create(['role' => UserRole::CONSUMER]));
    }

    $this->get(route('explorer.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('explorer/Index')
            ->missing('allBusinesses')
            ->missing('myBusinesses')
            ->missing('trashBusinesses')
        );
})->with([false, true]);

test('owners receive their own listings and lazily requested trash', function () {
    $this->listing->delete();
    $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($this->owner)->get(route('explorer.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('explorer/Index')
            ->has('myBusinesses', 0)
            ->has('trashBusinesses', 1)
            ->where('trashBusinesses.0.id', $this->listing->id)
        );

    $this->actingAs($otherOwner)->get(route('explorer.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'explorer/Index',
        'X-Inertia-Partial-Data' => 'trashBusinesses',
    ])->assertOk()
        ->assertJsonCount(0, 'props.trashBusinesses')
        ->assertJsonMissingPath('props.myBusinesses');
});

test('consumers cannot request owner data with a partial reload', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::CONSUMER]));
    $this->get(route('explorer.index'))->assertOk();

    $this->get(route('explorer.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'explorer/Index',
        'X-Inertia-Partial-Data' => 'myBusinesses,trashBusinesses',
    ])->assertOk()
        ->assertJsonMissingPath('props.myBusinesses')
        ->assertJsonMissingPath('props.trashBusinesses');
});

test('the explorer validates and retains its initial category', function () {
    $this->get(route('explorer.index', ['category' => 'Coffee']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedCategory', 'Coffee')->missing('allBusinesses')
        );

    $this->getJson(route('explorer.index', ['category' => ['invalid']]))
        ->assertUnprocessable()->assertJsonValidationErrors('category');
});

test('obsolete dashboard urls are removed', function (string $url) {
    $this->get($url)->assertNotFound();
})->with(['/dashboard', '/owner/dashboard', '/consumer/dashboard']);

test('nearby search retains combined filters and public fields', function () {
    $this->getJson(route('businesses.search', [
        'lat' => 14.6, 'lng' => 121.0, 'radius' => 0.5,
        'keyword' => 'coffee', 'category' => 'Coffee',
    ]))->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->listing->id)
        ->assertJsonPath('data.0.category.name', 'Coffee')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'markers')
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonMissingPath('markers.0.description')
        ->assertJsonMissingPath('markers.0.hours')
        ->assertJsonMissingPath('markers.0.user_id');
});

test('search paginates records but retains every matching lightweight pin', function () {
    for ($index = 1; $index <= 24; $index++) {
        $business = $this->listing->replicate();
        $business->fill(['name' => "Coffee {$index}", 'slug' => "coffee-{$index}", 'lat' => 14.6 + $index * 0.0001])->save();
    }

    $outside = $this->listing->replicate();
    $outside->fill(['slug' => 'outside', 'lat' => 15.0])->save();
    $deleted = $this->listing->replicate();
    $deleted->fill(['slug' => 'deleted'])->save();
    $deleted->delete();

    $query = ['lat' => 14.6, 'lng' => 121.0, 'radius' => 0.5, 'category' => 'Coffee'];
    $first = $this->getJson(route('businesses.search', $query))
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonCount(25, 'markers')
        ->assertJsonPath('meta.total', 25)->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.id', $this->listing->id);

    $second = $this->getJson(route('businesses.search', [...$query, 'page' => 2, 'include_markers' => 0]))
        ->assertOk()->assertJsonCount(5, 'data')->assertJsonMissingPath('markers')
        ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.total', 25);

    expect(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
    expect(array_column($first->json('markers'), 'id'))->not->toContain($outside->id, $deleted->id);
    expect(array_column($second->json('data'), 'distance'))->toBe(array_values(collect($second->json('data'))->pluck('distance')->sort()->all()));
});

test('search validates pagination and bounded radius', function (array $invalid) {
    $this->getJson(route('businesses.search', [...['lat' => 14.6, 'lng' => 121, 'radius' => 0.5], ...$invalid]))
        ->assertUnprocessable();
})->with([
    [['page' => 0]], [['page' => 1.5]], [['include_markers' => 'invalid']], [['radius' => 51]],
]);

test('an empty nearby search has usable pagination metadata', function () {
    $this->getJson(route('businesses.search', ['lat' => 10, 'lng' => 120, 'radius' => 0.5]))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonCount(0, 'markers')
        ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1);
});

test('saved pages beyond the remaining results return the last available page', function () {
    $this->getJson(route('businesses.search', ['lat' => 14.6, 'lng' => 121, 'radius' => 0.5, 'page' => 10]))
        ->assertOk()->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)->assertJsonCount(1, 'data');
});

test('discovery shortcuts expose documented category mappings to the explorer', function () {
    $this->get(route('explorer.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('discoveryShortcuts', 3)
        ->where('discoveryShortcuts.0.id', 'kape-muna')
        ->where('discoveryShortcuts.0.categories', ['Coffee Shops & Cafes'])
        ->where('discoveryShortcuts.1.categories', ['Carinderia & Eatery', 'Fast Food & Restaurant', 'Bakeries'])
    );
});

test('shortcuts combine their categories with keyword and radius for records and pins', function (string $shortcut) {
    $expected = [];
    foreach (DiscoveryShortcut::from($shortcut)->categories() as $index => $name) {
        $category = Category::create(['name' => $name, 'icon' => 'store', 'color' => '#2563eb']);
        $business = $this->listing->replicate();
        $business->fill(['category_id' => $category->id, 'name' => "Neighborhood {$index}", 'slug' => "shortcut-{$index}"])->save();
        $expected[] = $business->id;
    }

    $outside = $business->replicate();
    $outside->fill(['slug' => 'outside-shortcut', 'lat' => 15])->save();
    $deleted = $business->replicate();
    $deleted->fill(['slug' => 'deleted-shortcut'])->save();
    $deleted->delete();
    $otherKeyword = $business->replicate();
    $otherKeyword->fill(['slug' => 'other-keyword', 'name' => 'Different name', 'description' => 'Other description'])->save();

    $result = $this->getJson(route('businesses.search', [
        'lat' => 14.6, 'lng' => 121, 'radius' => 0.5, 'keyword' => 'Neighborhood',
        'category' => 'all', 'shortcut' => $shortcut,
    ]))->assertOk()->assertJsonPath('meta.total', count($expected));

    expect(array_column($result->json('data'), 'id'))->toEqualCanonicalizing($expected)
        ->and(array_column($result->json('markers'), 'id'))->toEqualCanonicalizing($expected);
})->with(['kape-muna', 'food-nearby', 'everyday-essentials']);

test('shortcuts retain pagination and can be replaced by a manual category', function () {
    $food = Category::create(['name' => 'Carinderia & Eatery', 'icon' => 'utensils', 'color' => '#2563eb']);
    for ($index = 0; $index < 25; $index++) {
        $business = $this->listing->replicate();
        $business->fill(['category_id' => $food->id, 'slug' => "food-{$index}"])->save();
    }
    $area = ['lat' => 14.6, 'lng' => 121, 'radius' => 0.5];
    $this->getJson(route('businesses.search', [...$area, 'shortcut' => 'food-nearby', 'page' => 2, 'include_markers' => 0]))
        ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.current_page', 2)->assertJsonMissingPath('markers');
    $this->getJson(route('businesses.search', [...$area, 'category' => 'Coffee']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->listing->id);
    $this->getJson(route('businesses.search', [...$area, 'category' => 'all']))
        ->assertOk()->assertJsonPath('meta.total', 26);
});

test('unknown shortcuts and conflicting category filters are rejected', function (array $filters) {
    $this->getJson(route('businesses.search', ['lat' => 14.6, 'lng' => 121, 'radius' => 0.5, ...$filters]))
        ->assertUnprocessable();
    $this->getJson(route('explorer.index', $filters))->assertUnprocessable();
})->with([
    [['shortcut' => 'unknown']],
    [['shortcut' => ['food-nearby']]],
    [['shortcut' => 'food-nearby', 'category' => 'Coffee']],
]);

test('shortcuts with missing categories return an honest empty result', function () {
    $this->getJson(route('businesses.search', ['lat' => 14.6, 'lng' => 121, 'radius' => 0.5, 'shortcut' => 'food-nearby']))
        ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data')->assertJsonCount(0, 'markers');
});

test('owner listings use five-record pages and return metrics for the visible page only', function () {
    for ($i = 1; $i <= 7; $i++) {
        $listing = $this->listing->replicate();
        $listing->fill(['name' => 'Coffee '.$i, 'slug' => 'coffee-'.$i, 'status' => $i % 2 === 0 ? 'active' : 'pending']);
        $listing->save();
    }

    $this->actingAs($this->owner)->get(route('explorer.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('myBusinesses', 5)->has('ownerInsights', 5)
            ->where('ownerPagination.total', 8)
            ->where('ownerPagination.per_page', 5)
            ->where('ownerPagination.current_page', 1)
            ->where('ownerPagination.last_page', 2)
            ->where('ownerPagination.from', 1)->where('ownerPagination.to', 5)
            ->missing("ownerInsights.{$this->listing->id}"));

    $this->get(route('explorer.index', ['listings_page' => 2]), [
        'X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'explorer/Index',
        'X-Inertia-Partial-Data' => 'myBusinesses,ownerInsights,ownerPagination',
    ])->assertOk()->assertJsonCount(3, 'props.myBusinesses')
        ->assertJsonPath('props.ownerPagination.current_page', 2)
        ->assertJsonPath('props.ownerPagination.from', 6)
        ->assertJsonPath('props.ownerPagination.to', 8)
        ->assertJsonMissingPath('props.trashBusinesses')
        ->assertJsonMissingPath('props.categories');
});

test('listing filters search only the owners nondeleted businesses', function () {
    $match = $this->listing->replicate();
    $match->fill(['name' => 'Special Coffee', 'slug' => 'special-coffee', 'status' => 'active']);
    $match->save();
    $deleted = $match->replicate();
    $deleted->slug = 'deleted-special-coffee';
    $deleted->save();
    $deleted->delete();
    $other = $match->replicate();
    $other->fill(['slug' => 'another-owner-coffee', 'user_id' => User::factory()->create(['role' => UserRole::OWNER])->id]);
    $other->save();

    $this->actingAs($this->owner)->get(route('explorer.index', [
        'listings_query' => '  Special  ', 'listings_status' => 'active', 'listings_page' => 100,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('myBusinesses', 1)->has('ownerInsights', 1)
        ->where('myBusinesses.0.id', $match->id)
        ->where('ownerPagination.total', 1)
        ->where('ownerPagination.current_page', 1)
        ->where('ownerPagination.query', 'Special')
        ->where('ownerPagination.status', 'active')
        ->where('selectedCategory', 'all'));

    $this->get(route('explorer.index', ['listings_query' => 'Special', 'listings_status' => 'pending']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('myBusinesses', 0)->has('ownerInsights', 0)
            ->where('ownerPagination.total', 0));
});

test('listing query validation rejects invalid pages statuses and oversized names', function (array $query, string $field) {
    $this->actingAs($this->owner)->getJson(route('explorer.index', $query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['listings_page' => 0], 'listings_page'],
    [['listings_status' => 'hidden'], 'listings_status'],
    [['listings_query' => ['invalid']], 'listings_query'],
    [['listings_query' => str_repeat('a', 101)], 'listings_query'],
]);

test('owner pagination is never exposed to guests or consumers', function () {
    $this->get(route('explorer.index', ['listings_page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('myBusinesses')->missing('ownerPagination')->missing('ownerInsights'));
    $this->actingAs(User::factory()->create(['role' => UserRole::CONSUMER]));
    $this->get(route('explorer.index', ['listings_query' => 'Coffee', 'listings_status' => 'pending']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('myBusinesses')->missing('ownerPagination')->missing('ownerInsights'));
});
