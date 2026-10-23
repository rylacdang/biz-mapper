<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Models\User;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->other = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id,
        'category_id' => $category->id,
        'name' => 'Collection Coffee',
        'slug' => 'collection-coffee',
        'description' => 'Coffee around the corner.',
        'address' => 'Sample Street',
        'lat' => 14.6,
        'lng' => 121.0,
        'cover_photo' => 'covers/sample.jpg',
        'hours' => [],
    ]);
    $this->saved = SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $this->business->id]);
    $this->collection = PlaceCollection::create(['user_id' => $this->consumer->id, 'name' => 'Kape spots']);
});

test('consumers create and rename private collections without accepting ownership fields', function () {
    $this->actingAs($this->consumer)->from(route('explorer.index'))
        ->post(route('consumer.collections.store'), ['name' => 'Weekend food trip', 'user_id' => $this->other->id, 'visibility' => 'public'])
        ->assertRedirect(route('explorer.index'));
    $this->assertDatabaseHas('place_collections', ['name' => 'Weekend food trip', 'user_id' => $this->consumer->id]);

    $this->put(route('consumer.collections.update', $this->collection), ['name' => 'Coffee favorites', 'user_id' => $this->other->id])->assertRedirect();
    $this->assertDatabaseHas('place_collections', ['id' => $this->collection->id, 'name' => 'Coffee favorites', 'user_id' => $this->consumer->id]);
    $this->getJson(route('consumer.collections.index'))->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.user_id')->assertHeader('Cache-Control', 'no-store, private');
    $this->postJson(route('consumer.collections.store'), ['name' => ''])->assertUnprocessable();
    $this->putJson(route('consumer.collections.update', $this->collection), ['name' => str_repeat('x', 81)])->assertUnprocessable();
});

test('collections cannot be read modified or populated by other users', function () {
    $this->actingAs($this->other)->getJson(route('consumer.collections.index'))->assertJsonCount(0, 'data');
    $this->getJson(route('consumer.collections.show', $this->collection))->assertForbidden();
    $this->getJson(route('consumer.collections.places.index', $this->collection))->assertForbidden();
    $this->putJson(route('consumer.collections.update', $this->collection), ['name' => 'Stolen'])->assertForbidden();
    $this->deleteJson(route('consumer.collections.destroy', $this->collection))->assertForbidden();
    $this->postJson(route('consumer.collections.places.store', [$this->collection, $this->saved]))->assertForbidden();
    $this->deleteJson(route('consumer.collections.places.destroy', [$this->collection, $this->saved]))->assertForbidden();
    $this->assertDatabaseHas('place_collections', ['id' => $this->collection->id, 'name' => 'Kape spots']);
});

test('guests and owners cannot use consumer collections', function () {
    $this->getJson(route('consumer.collections.index'))->assertUnauthorized();
    $this->postJson(route('consumer.collections.store'), ['name' => 'Not allowed'])->assertUnauthorized();
    $this->getJson(route('consumer.collections.show', $this->collection))->assertUnauthorized();
    $this->actingAs($this->owner)->getJson(route('consumer.collections.index'))->assertForbidden();
    $this->postJson(route('consumer.collections.store'), ['name' => 'Not allowed'])->assertForbidden();
    $this->postJson(route('consumer.collections.places.store', [$this->collection, $this->saved]))->assertForbidden();
});

test('membership requires an owned save and prevents duplicate additions', function () {
    $otherSave = SavedBusiness::create(['user_id' => $this->other->id, 'business_id' => $this->business->id]);
    $this->actingAs($this->consumer)->postJson(route('consumer.collections.places.store', [$this->collection, $otherSave]))->assertForbidden();

    foreach ([1, 2] as $attempt) {
        $this->post(route('consumer.collections.places.store', [$this->collection, $this->saved]))->assertRedirect();
    }

    $this->assertDatabaseCount('collection_places', 1);
    $this->getJson(route('consumer.collections.show', $this->collection))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->business->id)
        ->assertJsonPath('data.0.saved_id', $this->saved->id)->assertJsonPath('data.0.in_collection', true)
        ->assertJsonPath('collection.places_count', 1)->assertJsonMissingPath('data.0.user_id');
    $this->getJson(route('consumer.collections.index'))->assertJsonPath('data.0.places_count', 1);
});

test('removing membership and deleting collections preserve the original saves', function () {
    $second = PlaceCollection::create(['user_id' => $this->consumer->id, 'name' => 'Another list']);
    $this->actingAs($this->consumer);

    foreach ([$this->collection, $second] as $collection) {
        $this->post(route('consumer.collections.places.store', [$collection, $this->saved]))->assertRedirect();
    }

    $this->delete(route('consumer.collections.places.destroy', [$this->collection, $this->saved]))->assertRedirect();
    $this->assertDatabaseCount('collection_places', 1);
    $this->assertDatabaseHas('collection_places', ['place_collection_id' => $second->id]);
    $this->assertDatabaseHas('saved_businesses', ['id' => $this->saved->id]);
    $this->delete(route('consumer.collections.destroy', $second))->assertRedirect();
    $this->assertDatabaseCount('collection_places', 0);
    $this->assertDatabaseHas('saved_businesses', ['id' => $this->saved->id]);
});

test('unsaving a business clears all of its collection memberships', function () {
    $this->actingAs($this->consumer)->post(route('consumer.collections.places.store', [$this->collection, $this->saved]))->assertRedirect();
    $this->delete(route('consumer.saved-places.destroy', $this->business))->assertRedirect();
    $this->assertDatabaseCount('collection_places', 0);
    $this->assertDatabaseCount('place_collections', 1);
    $this->getJson(route('consumer.collections.show', $this->collection))->assertJsonCount(0, 'data');
});

test('unavailable businesses are excluded from counts and return after restoration', function () {
    $this->actingAs($this->consumer)->post(route('consumer.collections.places.store', [$this->collection, $this->saved]))->assertRedirect();
    $this->business->delete();
    $this->getJson(route('consumer.collections.show', $this->collection))->assertJsonCount(0, 'data')->assertJsonPath('collection.places_count', 0);
    $this->getJson(route('consumer.collections.index'))->assertJsonPath('data.0.places_count', 0);
    $this->getJson(route('consumer.collections.places.index', $this->collection))->assertJsonCount(0, 'data');
    $this->assertDatabaseCount('collection_places', 1);
    $this->business->restore();
    $this->getJson(route('consumer.collections.show', $this->collection))->assertJsonCount(1, 'data');
});

test('collection members and picker paginate without exposing another consumers saves', function () {
    $this->actingAs($this->consumer);

    for ($number = 1; $number <= 21; $number++) {
        $business = $this->business->replicate();
        $business->slug = 'collection-coffee-'.$number;
        $business->save();
        $save = SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $business->id]);
        $this->post(route('consumer.collections.places.store', [$this->collection, $save]))->assertRedirect();
    }

    $this->getJson(route('consumer.collections.show', $this->collection))->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21);
    $this->getJson(route('consumer.collections.show', [$this->collection, 'page' => 2]))->assertJsonCount(1, 'data');
    $this->getJson(route('consumer.collections.places.index', $this->collection))->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 22);
    $this->getJson(route('consumer.collections.places.index', [$this->collection, 'page' => 2]))
        ->assertJsonCount(2, 'data')->assertJsonPath('data.1.in_collection', false);
    $this->getJson(route('consumer.collections.index', ['page' => 0]))->assertUnprocessable();
    $this->getJson(route('consumer.collections.show', 999999))->assertNotFound();

    $oldestMember = SavedBusiness::where('user_id', $this->consumer->id)->where('id', '!=', $this->saved->id)->orderBy('id')->firstOrFail();
    $this->delete(route('consumer.collections.places.destroy', [$this->collection, $oldestMember]))->assertRedirect();
    $this->getJson(route('consumer.collections.show', [$this->collection, 'page' => 2]))->assertJsonPath('meta.current_page', 1)->assertJsonCount(20, 'data');
});

test('collection counts use bounded queries rather than one query per collection', function () {
    for ($number = 1; $number <= 21; $number++) {
        PlaceCollection::create(['user_id' => $this->consumer->id, 'name' => 'List '.$number]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    $results = app(PlaceCollectionRepositoryInterface::class)->forUser($this->consumer->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(2);
    expect($results->count())->toBe(20);
    expect($results->total())->toBe(22);
});
