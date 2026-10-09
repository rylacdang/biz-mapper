<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
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
            ->has('allBusinesses', 1)
            ->where('allBusinesses.0.id', $this->listing->id)
            ->missing('allBusinesses.0.user_id')
            ->missing('allBusinesses.0.status')
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
            ->where('selectedCategory', 'Coffee')->has('allBusinesses', 1)
        );

    $this->getJson(route('explorer.index', ['category' => ['invalid']]))
        ->assertUnprocessable()->assertJsonValidationErrors('category');
});

test('obsolete dashboard urls are removed', function (string $url) {
    $this->get($url)->assertNotFound();
})->with(['/dashboard', '/owner/dashboard', '/consumer/dashboard']);

test('nearby search retains combined filters and its public response contract', function () {
    $this->listing->load('category')->setAttribute('distance', 0.123);
    $this->mock(BusinessRepositoryInterface::class)
        ->shouldReceive('searchByRadius')
        ->once()->with(14.6, 121.0, 0.5, 'coffee', 'Coffee')
        ->andReturn(new Collection([$this->listing]));

    $this->getJson(route('businesses.search', [
        'lat' => 14.6, 'lng' => 121.0, 'radius' => 0.5,
        'keyword' => 'coffee', 'category' => 'Coffee',
    ]))->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->listing->id)
        ->assertJsonPath('data.0.distance', 0.12)
        ->assertJsonPath('data.0.category.name', 'Coffee')
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.status');
});
