<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\PlaceCollection;
use App\Models\SavedBusiness;
use App\Models\User;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Services\CollectionOrderingService;
use App\Services\CollectionSharingService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

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

test('collection descriptions can be created edited cleared and shared safely', function () {
    $this->actingAs($this->consumer)->post(route('consumer.collections.store'), [
        'name' => 'Weekend walk', 'description' => 'Coffee before the park.',
    ])->assertRedirect();
    $this->assertDatabaseHas('place_collections', ['name' => 'Weekend walk', 'description' => 'Coffee before the park.']);
    $this->put(route('consumer.collections.update', $this->collection), [
        'name' => 'Kape spots', 'description' => 'Our favorite neighborhood stops.',
    ])->assertRedirect();
    $this->getJson(route('consumer.collections.show', $this->collection))
        ->assertJsonPath('collection.description', 'Our favorite neighborhood stops.');
    $this->putJson(route('consumer.collections.update', $this->collection), [
        'name' => 'Kape spots', 'description' => str_repeat('a', 501),
    ])->assertUnprocessable()->assertJsonValidationErrors('description');
    app(CollectionSharingService::class)->update($this->collection, true);
    $this->get(route('collections.show', $this->collection->fresh()->share_token))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('collection.description', 'Our favorite neighborhood stops.'));
    $this->put(route('consumer.collections.update', $this->collection), [
        'name' => 'Kape spots', 'description' => '',
    ])->assertRedirect();
    $this->assertDatabaseHas('place_collections', ['id' => $this->collection->id, 'description' => null]);
});

test('adjacent moves cross collection pages and keep public and private order identical', function () {
    $ordering = app(CollectionOrderingService::class);
    $ordering->add($this->collection, $this->saved->id);
    $saves = collect([$this->saved]);
    foreach (range(1, 21) as $index) {
        $business = $this->business->replicate();
        $business->slug = 'ordered-stop-'.$index;
        $business->save();
        $saved = SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $business->id]);
        $ordering->add($this->collection, $saved->id);
        $saves->push($saved);
    }
    $this->actingAs($this->consumer);
    $url = fn ($save) => route('consumer.collections.places.update', [$this->collection, $save]);
    $this->putJson($url($saves[20]), ['direction' => 'up', 'page' => 1])->assertOk()
        ->assertJsonPath('data.19.id', $saves[20]->business_id)
        ->assertJsonPath('collection.id', $this->collection->id)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Location');
    $this->getJson(route('consumer.collections.show', [$this->collection, 'page' => 1]))
        ->assertJsonPath('data.19.id', $saves[20]->business_id);
    $this->getJson(route('consumer.collections.show', [$this->collection, 'page' => 2]))
        ->assertJsonPath('data.0.id', $saves[19]->business_id)->assertJsonPath('meta.total', 22);
    $repo = app(PlaceCollectionRepositoryInterface::class);
    expect($repo->publicPlaces($this->collection, 1)->pluck('id')->all())
        ->toBe($repo->places($this->collection, 1)->pluck('id')->all());
    $this->putJson($url($saves[20]), ['direction' => 'down', 'page' => 2])->assertOk()
        ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.id', $saves[20]->business_id);
    $this->getJson(route('consumer.collections.show', [$this->collection, 'page' => 2]))
        ->assertJsonPath('data.0.id', $saves[20]->business_id);
    $ordering->add($this->collection, $saves[0]->id);
    expect($repo->places($this->collection)->first()->id)->toBe($saves[0]->business_id);
    expect(DB::table('collection_places')->where('place_collection_id', $this->collection->id)->count())->toBe(22);
});

test('collection moves require an owned existing member and a valid direction', function () {
    $url = route('consumer.collections.places.update', [$this->collection, $this->saved]);
    $this->putJson($url, ['direction' => 'up'])->assertUnauthorized();
    $this->actingAs($this->other)->putJson($url, ['direction' => 'up'])->assertForbidden();
    $this->actingAs($this->owner)->putJson($url, ['direction' => 'up'])->assertForbidden();
    $this->actingAs($this->consumer)->putJson($url, ['direction' => 'sideways'])->assertUnprocessable();
    $this->putJson($url, ['direction' => 'up', 'page' => 0])->assertUnprocessable();
    $this->putJson($url, ['direction' => 'up'])->assertNotFound();
    app(CollectionOrderingService::class)->add($this->collection, $this->saved->id);
    $this->putJson($url, ['direction' => 'up'])->assertOk();
    $this->putJson($url, ['direction' => 'down'])->assertOk();
    expect(app(PlaceCollectionRepositoryInterface::class)->places($this->collection)->total())->toBe(1);
    $otherSave = SavedBusiness::create(['user_id' => $this->other->id, 'business_id' => $this->business->id]);
    $this->putJson(route('consumer.collections.places.update', [$this->collection, $otherSave]), ['direction' => 'up'])->assertForbidden();
});

test('legacy collection order is preserved and moving skips unavailable businesses', function () {
    $saves = collect([$this->saved]);
    foreach (range(1, 2) as $index) {
        $business = $this->business->replicate();
        $business->slug = 'legacy-stop-'.$index;
        $business->save();
        $saves->push(SavedBusiness::create(['user_id' => $this->consumer->id, 'business_id' => $business->id]));
    }
    $this->collection->savedPlaces()->attach($saves->pluck('id'));
    $repo = app(PlaceCollectionRepositoryInterface::class);
    expect($repo->places($this->collection)->pluck('id')->all())->toBe($saves->reverse()->pluck('business_id')->values()->all());
    $hidden = Business::findOrFail($saves[1]->business_id);
    $hidden->delete();
    app(CollectionOrderingService::class)->move($this->collection, $saves[0]->id, 'up');
    expect($repo->places($this->collection)->pluck('id')->all())->toBe([$saves[0]->business_id, $saves[2]->business_id]);
    $hidden->restore();
    expect($repo->places($this->collection)->pluck('id')->all())->toBe([$saves[0]->business_id, $saves[1]->business_id, $saves[2]->business_id]);
});
