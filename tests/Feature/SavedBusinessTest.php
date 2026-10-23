<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\SavedBusiness;
use App\Models\User;
use App\Services\SavedBusinessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['acos' => 'acos', 'cos' => 'cos', 'sin' => 'sin', 'radians' => 'deg2rad'] as $name => $callback) {
            DB::connection()->getPdo()->sqliteCreateFunction($name, $callback, 1);
        }
    }

    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->otherConsumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);

    $this->business = Business::create([
        'user_id' => $this->owner->id,
        'category_id' => $category->id,
        'name' => 'Saved Coffee',
        'slug' => 'saved-coffee',
        'description' => 'Coffee nearby.',
        'address' => 'Sample Street',
        'lat' => 14.6,
        'lng' => 121.0,
        'cover_photo' => 'covers/sample.jpg',
        'hours' => [],
    ]);
});

test('saving is idempotent and ignores client supplied ownership', function () {
    $this->actingAs($this->consumer);

    foreach ([1, 2] as $attempt) {
        $this->from(route('explorer.index'))->post(route('consumer.saved-places.store', $this->business), [
            'user_id' => $this->otherConsumer->id,
        ])->assertRedirect(route('explorer.index'));
    }

    $this->assertDatabaseCount('saved_businesses', 1);
    $this->assertDatabaseHas('saved_businesses', ['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
});

test('saved places are private and unsaving cannot affect another consumer', function () {
    foreach ([$this->consumer, $this->otherConsumer] as $user) {
        SavedBusiness::create(['user_id' => $user->id, 'business_id' => $this->business->id]);
    }

    $this->actingAs($this->consumer)->getJson(route('consumer.saved-places.index'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_saved', true)
        ->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('data.0.saved_businesses')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->delete(route('consumer.saved-places.destroy', $this->business))->assertRedirect();
    $this->delete(route('consumer.saved-places.destroy', $this->business))->assertRedirect();
    $this->assertDatabaseCount('saved_businesses', 1);
    $this->assertDatabaseHas('saved_businesses', ['user_id' => $this->otherConsumer->id]);
    $this->getJson(route('consumer.saved-places.index'))->assertJsonCount(0, 'data');
    $this->actingAs($this->otherConsumer)->getJson(route('consumer.saved-places.index'))->assertJsonCount(1, 'data');
});

test('guests must log in and owners cannot use consumer bookmarks', function () {
    $this->getJson(route('consumer.saved-places.index'))->assertUnauthorized();
    $this->post(route('consumer.saved-places.store', $this->business))->assertRedirect(route('login'));
    $this->deleteJson(route('consumer.saved-places.destroy', $this->business))->assertUnauthorized();

    $this->actingAs($this->owner)->getJson(route('consumer.saved-places.index'))->assertForbidden();
    $this->postJson(route('consumer.saved-places.store', $this->business))->assertForbidden();
    $this->deleteJson(route('consumer.saved-places.destroy', $this->business))->assertForbidden();
    $this->assertDatabaseCount('saved_businesses', 0);
});

test('unavailable places are hidden and reappear after restoration', function () {
    SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
    $this->business->delete();
    $this->actingAs($this->consumer)->getJson(route('consumer.saved-places.index'))->assertJsonCount(0, 'data');
    $this->postJson(route('consumer.saved-places.store', $this->business))->assertNotFound();
    $this->postJson(route('consumer.saved-places.store', 999999))->assertNotFound();

    $this->business->restore();
    $this->getJson(route('consumer.saved-places.index'))->assertJsonCount(1, 'data');

    $this->business->delete();
    $this->delete(route('consumer.saved-places.destroy', $this->business))->assertRedirect();
    $this->assertDatabaseCount('saved_businesses', 0);
});

test('saved places paginate newest first and recover an empty final page', function () {
    for ($number = 1; $number <= 21; $number++) {
        $business = $this->business->replicate();
        $business->slug = 'saved-coffee-'.$number;
        $business->save();
        SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $business->id]);
    }

    $this->actingAs($this->consumer)->getJson(route('consumer.saved-places.index'))
        ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)
        ->assertJsonPath('data.0.slug', 'saved-coffee-21');
    $this->getJson(route('consumer.saved-places.index', ['page' => 2]))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'saved-coffee-1');

    $oldest = Business::where('slug', 'saved-coffee-1')->firstOrFail();
    $this->delete(route('consumer.saved-places.destroy', $oldest))->assertRedirect();
    $this->getJson(route('consumer.saved-places.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.current_page', 1);
    $this->getJson(route('consumer.saved-places.index', ['page' => 0]))->assertUnprocessable();
});

test('public profile and search expose only the current consumers bookmark state', function () {
    SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
    $search = route('businesses.search', ['lat' => 14.6, 'lng' => 121.0, 'radius' => 0.5]);
    $profile = route('businesses.show', $this->business->slug);

    $this->actingAs($this->consumer)->getJson($search)->assertJsonPath('data.0.is_saved', true);
    $this->getJson($profile)->assertJsonPath('data.is_saved', true)->assertHeader('Cache-Control', 'no-store, private');
    $this->get($profile)->assertInertia(fn (AssertableInertia $page) => $page->where('business.is_saved', true));
    $this->get(route('explorer.index', ['destination' => $this->business->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('directionsBusiness.is_saved', true));

    $this->actingAs($this->otherConsumer)->getJson($search)->assertJsonPath('data.0.is_saved', false);
    $this->getJson($profile)->assertJsonPath('data.is_saved', false);
    $this->actingAs($this->owner)->getJson($search)->assertJsonPath('data.0.is_saved', false);
    auth()->forgetGuards();
    $this->getJson($search)->assertJsonPath('data.0.is_saved', false);
    $this->getJson($profile)->assertJsonPath('data.is_saved', false);
});

test('bookmark decoration uses one query for a page of businesses', function () {
    SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
    $businesses = new Collection([$this->business, $this->business->replicate()]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    app(SavedBusinessService::class)->markSaved($businesses, $this->consumer);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1);
    expect($businesses->first()->is_saved)->toBeTrue();
    expect($businesses->last()->is_saved)->toBeFalse();
});
