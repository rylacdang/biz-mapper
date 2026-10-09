<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create([
        'name' => 'Coffee Shops & Cafes',
        'icon' => 'coffee',
        'color' => '#2563eb',
    ]);

    $this->business = Business::create([
        'user_id' => $owner->id,
        'category_id' => $category->id,
        'name' => 'Neighborhood Coffee',
        'slug' => 'neighborhood-coffee',
        'description' => 'Coffee and fresh bread around the corner.',
        'address' => 'Sample Street, Quezon City',
        'lat' => 14.6865,
        'lng' => 121.0906,
        'cover_photo' => 'covers/coffee.jpg',
        'phone' => '09171234567',
        'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
    ]);
});

test('guests can load a profile with only public business fields', function () {
    $this->get(route('businesses.show', $this->business->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('businesses/Show')
            ->where('business.name', 'Neighborhood Coffee')
            ->where('business.lat', 14.6865)
            ->where('business.category.name', 'Coffee Shops & Cafes')
            ->where('business.hours.monday.closed', false)
            ->where('metadata.url', route('businesses.show', $this->business->slug))
            ->missing('business.user_id')
            ->missing('business.status')
            ->missing('business.region_code')
            ->missing('business.created_at')
        );

    expect(Gate::allows('view', $this->business))->toBeTrue();
});

test('missing and deleted profiles return not found', function (bool $deleted) {
    $slug = 'missing-business';

    if ($deleted) {
        $slug = $this->business->slug;
        $this->business->delete();
    }

    $this->get(route('businesses.show', $slug))->assertNotFound();
})->with([false, true]);

test('a query parameter cannot replace the profile slug', function () {
    $this->get(route('businesses.show', $this->business->slug).'?slug=missing-business')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('business.slug', $this->business->slug)
        );
});

test('profile reads reflect edits and restoration immediately', function () {
    $url = route('businesses.show', $this->business->slug);

    $this->get($url)->assertOk();
    $this->business->update(['phone' => '09179999999']);

    $this->get($url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('business.phone', '09179999999')
        );

    $this->business->delete();
    $this->get($url)->assertNotFound();
    $this->business->restore();
    $this->get($url)->assertOk();
});

test('initial html includes escaped profile sharing metadata', function () {
    $this->business->update(['name' => 'Coffee "Corner" <Shop>']);

    $this->get(route('businesses.show', $this->business->slug))
        ->assertOk()
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('Coffee &quot;Corner&quot; &lt;Shop&gt; | BizMapper', false)
        ->assertSee('rel="canonical"', false);
});
