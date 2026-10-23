<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Models\User;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Services\CollectionSharingService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->other = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id,
        'category_id' => $category->id,
        'name' => 'Shared Coffee',
        'slug' => 'shared-coffee',
        'description' => 'Coffee nearby.',
        'address' => 'Sample Street',
        'lat' => 14.6,
        'lng' => 121.0,
        'cover_photo' => 'covers/sample.jpg',
        'hours' => [],
    ]);
    $this->collection = PlaceCollection::create(['user_id' => $this->consumer->id, 'name' => 'Coffee favorites']);
    $this->saved = SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
    app(PlaceCollectionRepositoryInterface::class)->addPlace($this->collection->id, $this->saved->id);
});

test('collections stay private until the owner explicitly publishes them', function () {
    expect($this->collection->fresh()->is_public)->toBeFalse();
    expect($this->collection->fresh()->share_token)->toBeNull();
    $this->actingAs($this->consumer)->post(route('consumer.collections.store'), [
        'name' => 'Private by default', 'is_public' => true, 'share_token' => (string) Str::uuid(),
    ])->assertRedirect();
    $created = PlaceCollection::where('name', 'Private by default')->firstOrFail();
    expect($created->is_public)->toBeFalse()->and($created->share_token)->toBeNull();
    $this->put(route('consumer.collections.update', $this->collection), ['name' => 'Renamed', 'is_public' => true])->assertRedirect();
    expect($this->collection->fresh()->is_public)->toBeFalse();
    $this->getJson(route('consumer.collections.index'))->assertJsonPath('data.0.share_url', null);

    $this->put(route('consumer.collections.sharing.update', $this->collection), ['is_public' => true])->assertRedirect();
    $published = $this->collection->fresh();
    expect($published->is_public)->toBeTrue()->and(Str::isUuid($published->share_token))->toBeTrue();
    $this->getJson(route('consumer.collections.show', $this->collection))
        ->assertJsonPath('collection.is_public', true)
        ->assertJsonPath('collection.share_url', route('collections.show', $published->share_token));
});

test('sharing changes require the collection owner and a valid explicit choice', function () {
    $url = route('consumer.collections.sharing.update', $this->collection);
    $this->putJson($url, ['is_public' => true])->assertUnauthorized();
    $this->actingAs($this->other)->putJson($url, ['is_public' => true])->assertForbidden();
    $this->actingAs($this->owner)->putJson($url, ['is_public' => true])->assertForbidden();
    $this->actingAs($this->consumer)->putJson($url, [])->assertUnprocessable();
    $this->putJson($url, ['is_public' => 'everyone'])->assertUnprocessable();
    expect($this->collection->fresh()->is_public)->toBeFalse();
});

test('guests can view published collections without private owner or membership fields', function () {
    app(CollectionSharingService::class)->update($this->collection, true);
    $token = $this->collection->fresh()->share_token;
    $this->get(route('collections.show', $token))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('collections/Show')
            ->where('collection.name', 'Coffee favorites')
            ->where('collection.places_count', 1)
            ->where('collection.map_url', route('explorer.index', ['collection' => $token]))
            ->where('places.data.0.id', $this->business->id)
            ->where('places.data.0.is_saved', false)
            ->missing('collection.id')->missing('collection.user_id')->missing('collection.user')
            ->missing('places.data.0.saved_id')->missing('places.data.0.in_collection')->missing('places.data.0.user_id')
        );
});

test('published collections open directly in the unified explorer for guests', function () {
    app(CollectionSharingService::class)->update($this->collection, true);
    $token = $this->collection->fresh()->share_token;
    $url = route('explorer.index', ['collection' => $token]);

    foreach ([1, 2] as $load) {
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('explorer/Index')->where('sharedCollection.share_token', $token)
                ->where('sharedPlaces.data.0.id', $this->business->id)
                ->where('sharedPlaces.data.0.is_saved', false)
                ->missing('sharedCollection.user_id')->missing('sharedPlaces.data.0.saved_id')
                ->missing('myBusinesses')->missing('trashBusinesses')
            );
    }
});

test('private and missing links never reveal collection data even to the owner', function () {
    $token = (string) Str::uuid();
    $this->collection->update(['share_token' => $token]);
    $this->get(route('collections.show', $token))->assertNotFound();
    $this->get(route('explorer.index', ['collection' => $token]))->assertNotFound();
    $this->actingAs($this->consumer)->get(route('collections.show', $token))->assertNotFound();
    $this->get(route('collections.show', (string) Str::uuid()))->assertNotFound();
    $this->get('/collections/not-a-valid-token')->assertNotFound();
});

test('making a collection private disables both links and retains the stable token', function () {
    $sharing = route('consumer.collections.sharing.update', $this->collection);
    $this->actingAs($this->consumer)->put($sharing, ['is_public' => true])->assertRedirect();
    $token = $this->collection->fresh()->share_token;
    $this->put($sharing, ['is_public' => true])->assertRedirect();
    expect($this->collection->fresh()->share_token)->toBe($token);
    $this->put($sharing, ['is_public' => false])->assertRedirect();
    $this->get(route('collections.show', $token))->assertNotFound();
    $this->get(route('explorer.index', ['collection' => $token]))->assertNotFound();
    $this->getJson(route('consumer.collections.show', $this->collection))->assertJsonPath('collection.share_url', null);
    $this->put($sharing, ['is_public' => true])->assertRedirect();
    expect($this->collection->fresh()->share_token)->toBe($token);
    $this->get(route('collections.show', $token))->assertOk();
});

test('public bookmark flags belong to the viewer rather than the collection creator', function () {
    app(CollectionSharingService::class)->update($this->collection, true);
    $url = route('collections.show', $this->collection->fresh()->share_token);
    $this->actingAs($this->other)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('places.data.0.is_saved', false));
    $this->actingAs($this->consumer)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('places.data.0.is_saved', true));
    $this->actingAs($this->owner)->get($url)->assertInertia(fn (AssertableInertia $page) => $page->where('places.data.0.is_saved', false));
});

test('public collection pagination is bounded and restores the requested map page', function () {
    $repo = app(PlaceCollectionRepositoryInterface::class);

    for ($number = 1; $number <= 21; $number++) {
        $business = $this->business->replicate();
        $business->slug = 'shared-coffee-'.$number;
        $business->save();
        $save = SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $business->id]);
        $repo->addPlace($this->collection->id, $save->id);
    }

    app(CollectionSharingService::class)->update($this->collection, true);
    $token = $this->collection->fresh()->share_token;
    $this->get(route('collections.show', $token))->assertInertia(fn (AssertableInertia $page) => $page->has('places.data', 20)->where('places.meta.total', 22));
    $this->get(route('collections.show', [$token, 'page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->has('places.data', 2)->where('places.meta.current_page', 2));
    $this->get(route('explorer.index', ['collection' => $token, 'collection_page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->has('sharedPlaces.data', 2)->where('sharedPlaces.meta.current_page', 2));
    $this->getJson(route('collections.show', [$token, 'page' => 0]))->assertUnprocessable();
    $this->getJson(route('explorer.index', ['collection' => $token, 'collection_page' => 0]))->assertUnprocessable();
});

test('public counts and content follow availability and membership changes', function () {
    app(CollectionSharingService::class)->update($this->collection, true);
    $url = route('collections.show', $this->collection->fresh()->share_token);
    $this->business->delete();
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page->has('places.data', 0)->where('collection.places_count', 0));
    $this->business->restore();
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page->has('places.data', 1));
    $this->saved->delete();
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page->has('places.data', 0)->where('collection.places_count', 0));
});

test('deleting a published collection disables its link without deleting bookmarks', function () {
    app(CollectionSharingService::class)->update($this->collection, true);
    $token = $this->collection->fresh()->share_token;
    $this->actingAs($this->consumer)->delete(route('consumer.collections.destroy', $this->collection))->assertRedirect();
    $this->get(route('collections.show', $token))->assertNotFound();
    $this->get(route('explorer.index', ['collection' => $token]))->assertNotFound();
    $this->assertDatabaseHas('saved_businesses', ['id' => $this->saved->id]);
});
