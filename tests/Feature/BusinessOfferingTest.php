<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessOfferingService;
use App\Services\BusinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->category = Category::create(['name' => 'Laundry', 'icon' => 'shirt', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $this->category->id,
        'name' => 'Sample Laundry', 'slug' => 'sample-laundry',
        'description' => 'Laundry in the neighborhood.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'cover_photo' => 'covers/sample.jpg', 'hours' => [],
    ]);
    $this->item = BusinessOffering::create([
        'business_id' => $this->business->id, 'type' => 'service', 'name' => 'Wash & fold',
    ]);
});

test('an owner can add a product or service with a photo and explicit price range', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.offerings.store', $this->business), [
        'type' => 'service', 'name' => 'Dry cleaning', 'description' => 'Includes pressing.',
        'photo' => UploadedFile::fake()->image('item.jpg'), 'price' => '80.00',
        'price_max' => '120.00', 'price_unit' => 'piece',
    ])->assertRedirect(route('explorer.index'))->assertSessionHasNoErrors();

    $item = BusinessOffering::where('name', 'Dry cleaning')->firstOrFail();
    expect($item->business_id)->toBe($this->business->id)
        ->and($item->price)->toBe('80.00')->and($item->price_max)->toBe('120.00')
        ->and($item->price_unit)->toBe('piece')->and($item->currency)->toBe('PHP');
    Storage::disk('public')->assertExists($item->photo);
});

test('optional prices and photos stay absent rather than becoming invented values', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.offerings.store', $this->business), [
        'type' => 'product', 'name' => 'Laundry bag', 'currency' => 'PHP', 'price_unit' => 'piece',
    ])->assertSessionHasNoErrors();

    $item = BusinessOffering::where('name', 'Laundry bag')->firstOrFail();
    expect($item->price)->toBeNull()->and($item->currency)->toBeNull()
        ->and($item->price_unit)->toBeNull()->and($item->photo)->toBeNull();
});

test('a zero price is preserved when explicitly supplied', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.offerings.store', $this->business), [
        'type' => 'service', 'name' => 'Pickup', 'price' => '0',
    ])->assertSessionHasNoErrors();
    $item = BusinessOffering::where('name', 'Pickup')->firstOrFail();
    expect($item->price)->toBe('0.00')->and($item->currency)->toBe('PHP');
});

test('editing a priced item assigns pesos without a currency input', function () {
    $this->actingAs($this->owner)->put(route('owner.businesses.offerings.update', [$this->business, $this->item]), [
        'type' => 'service', 'name' => 'Wash & fold', 'price' => '95.50',
    ])->assertSessionHasNoErrors();

    expect($this->item->refresh()->price)->toBe('95.50')->and($this->item->currency)->toBe('PHP');
});

test('item updates support upload method spoofing and clean up replaced photos', function () {
    Storage::disk('public')->put('business-offerings/old.jpg', 'old image');
    $this->item->update(['photo' => 'business-offerings/old.jpg']);
    $url = route('owner.businesses.offerings.update', [$this->business, $this->item]);

    $this->actingAs($this->owner)->post($url, [
        '_method' => 'PUT', 'type' => 'service', 'name' => 'Wash & fold updated',
        'photo' => UploadedFile::fake()->image('new.png'), 'remove_photo' => false,
    ])->assertSessionHasNoErrors();

    $this->item->refresh();
    Storage::disk('public')->assertMissing('business-offerings/old.jpg');
    Storage::disk('public')->assertExists($this->item->photo);

    $photo = $this->item->photo;
    $this->put($url, ['type' => 'service', 'name' => 'Wash & fold', 'remove_photo' => true])->assertSessionHasNoErrors();
    expect($this->item->refresh()->photo)->toBeNull();
    Storage::disk('public')->assertMissing($photo);
});

test('saving without a replacement keeps the existing item photo', function () {
    $this->item->update(['photo' => 'business-offerings/keep.jpg']);
    Storage::disk('public')->put($this->item->photo, 'image');
    $this->actingAs($this->owner)->put(route('owner.businesses.offerings.update', [$this->business, $this->item]), [
        'type' => 'service', 'name' => 'Updated service',
    ])->assertSessionHasNoErrors();
    expect($this->item->refresh()->photo)->toBe('business-offerings/keep.jpg');
    Storage::disk('public')->assertExists($this->item->photo);
});

test('invalid catalog values are rejected', function (array $invalid) {
    $this->actingAs($this->owner)->post(route('owner.businesses.offerings.store', $this->business), [
        ...['type' => 'service', 'name' => 'Service'], ...$invalid,
    ])->assertSessionHasErrors();
})->with([
    [['type' => 'invalid']], [['name' => '']], [['price' => '-1', 'currency' => 'PHP']],
    [['price' => '10.123', 'currency' => 'PHP']], [['price' => '100', 'price_max' => '80', 'currency' => 'PHP']],
    [['price_max' => '80']], [['price' => '80', 'currency' => 'USD']], [['price' => '80', 'currency' => 'EUR']], [['price' => '80', 'currency' => 'invalid']],
    [['photo' => 'another-owner.jpg']],
]);

test('other owners and consumers cannot manage this catalog', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('owner.businesses.offerings.index', $this->business))->assertForbidden();
    $this->post(route('owner.businesses.offerings.store', $this->business), ['type' => 'product', 'name' => 'Unauthorized'])->assertForbidden();
    $this->put(route('owner.businesses.offerings.update', [$this->business, $this->item]), ['type' => 'service', 'name' => 'Unauthorized'])->assertForbidden();
    $this->delete(route('owner.businesses.offerings.destroy', [$this->business, $this->item]))->assertForbidden();
})->with(['owner', 'consumer']);

test('scoped routes reject items from a different business', function () {
    $another = $this->business->replicate();
    $another->slug = 'another';
    $another->save();

    $this->actingAs($this->owner)->put(route('owner.businesses.offerings.update', [$another, $this->item]), [
        'type' => 'service', 'name' => 'Wrong business',
    ])->assertNotFound();
    $this->delete(route('owner.businesses.offerings.destroy', [$another, $this->item]))->assertNotFound();
});

test('guests can read bounded public catalogs and initial profile items', function () {
    for ($i = 0; $i < 24; $i++) {
        BusinessOffering::create(['business_id' => $this->business->id, 'type' => 'product', 'name' => "Product {$i}"]);
    }

    $this->getJson(route('businesses.offerings.index', $this->business->slug))
        ->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 25)
        ->assertJsonMissingPath('data.0.business_id');
    $this->getJson(route('businesses.offerings.index', ['business' => $this->business->slug, 'page' => 3]))
        ->assertOk()->assertJsonCount(1, 'data');
    $this->get(route('businesses.show', $this->business->slug))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('offerings.data', 12)->where('offerings.meta.total', 25));
    $this->actingAs($this->owner)->getJson(route('owner.businesses.offerings.index', $this->business))
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 25);
});

test('deleting and restoring a business hides and restores its catalog', function () {
    $this->business->delete();
    $this->getJson(route('businesses.offerings.index', $this->business->slug))->assertNotFound();
    $this->getJson(route('businesses.show', $this->business->slug))->assertNotFound();
    expect(BusinessOffering::find($this->item->id))->not->toBeNull();

    $this->actingAs($this->owner)->post(route('owner.businesses.restore', $this->business->id))->assertRedirect();
    $this->getJson(route('businesses.offerings.index', $this->business->slug))->assertOk()->assertJsonCount(1, 'data');
});

test('deleting an item removes its stored photo', function () {
    Storage::disk('public')->put('business-offerings/delete.jpg', 'image');
    $this->item->update(['photo' => 'business-offerings/delete.jpg']);
    $this->actingAs($this->owner)->delete(route('owner.businesses.offerings.destroy', [$this->business, $this->item]))->assertRedirect();
    $this->assertDatabaseMissing('business_offerings', ['id' => $this->item->id]);
    Storage::disk('public')->assertMissing('business-offerings/delete.jpg');
});

test('a failed write does not leave a newly uploaded item photo behind', function () {
    $this->mock(BusinessOfferingRepositoryInterface::class)->shouldReceive('create')->once()->andThrow(new RuntimeException('Failed write'));
    $service = app(BusinessOfferingService::class);
    expect(fn () => $service->create($this->business, ['type' => 'service', 'name' => 'Service', 'photo' => UploadedFile::fake()->image('failed.jpg')]))
        ->toThrow(RuntimeException::class);
    expect(Storage::disk('public')->allFiles('business-offerings'))->toBe([]);
});

test('social links accept safe optional urls and reject unknown platforms and protocols', function () {
    $url = route('owner.businesses.update', $this->business);
    $this->actingAs($this->owner)->put($url, ['social_links' => ['facebook' => 'https://www.facebook.com/sample', 'instagram' => null]])->assertSessionHasNoErrors();
    $this->getJson(route('businesses.show', $this->business->slug))->assertJsonPath('data.social_links.facebook', 'https://www.facebook.com/sample');
    $this->put($url, ['social_links' => ['facebook' => 'javascript:alert(1)']])->assertSessionHasErrors('social_links.facebook');
    $this->put($url, ['social_links' => ['unknown' => 'https://example.com']])->assertSessionHasErrors('social_links');
    $this->put($url, ['social_links' => ['facebook' => null]])->assertSessionHasNoErrors();
    expect($this->business->refresh()->social_links['facebook'])->toBeNull();
});

test('new catalog items appear first in the owner management list', function () {
    $latest = BusinessOffering::create(['business_id' => $this->business->id, 'type' => 'service', 'name' => 'New service']);
    $this->actingAs($this->owner)->getJson(route('owner.businesses.offerings.index', $this->business))
        ->assertOk()->assertJsonPath('data.0.id', $latest->id);
});

test('registration saves draft items together with the new listing', function () {
    $repository = Mockery::mock(app(BusinessRepositoryInterface::class));
    $repository->shouldReceive('getSlugPatternCount')->with('new-shop')->andReturn(0);
    app()->instance(BusinessRepositoryInterface::class, $repository);

    $this->actingAs($this->owner)->post(route('owner.businesses.store'), [
        'category_id' => $this->category->id, 'name' => 'New Shop',
        'description' => 'A new neighborhood shop.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
        'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
        'offerings' => [
            ['type' => 'service', 'name' => 'Wash', 'photo' => UploadedFile::fake()->image('wash.jpg'), 'price' => '80', 'price_max' => '120'],
            ['type' => 'product', 'name' => 'Bag'],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('explorer.index'));

    $created = Business::where('slug', 'new-shop')->firstOrFail();
    expect($created->offerings)->toHaveCount(2);
    $priced = $created->offerings->firstWhere('name', 'Wash');
    expect($priced->price)->toBe('80.00')->and($priced->price_max)->toBe('120.00')
        ->and($priced->currency)->toBe('PHP');
    Storage::disk('public')->assertExists($priced->photo);
    expect($created->offerings->firstWhere('name', 'Bag')->price)->toBeNull();
});

test('registration rejects foreign currencies in draft items', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.store'), [
        'category_id' => $this->category->id, 'name' => 'Foreign currency shop',
        'description' => 'A neighborhood shop.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
        'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
        'offerings' => [['type' => 'product', 'name' => 'Bag', 'price' => '100', 'currency' => 'USD']],
    ])->assertSessionHasErrors('offerings.0.currency');

    $this->assertDatabaseMissing('businesses', ['name' => 'Foreign currency shop']);
});

test('invalid draft items do not create a listing or upload its photos', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.store'), [
        'category_id' => $this->category->id, 'name' => 'Invalid shop',
        'description' => 'A new neighborhood shop.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
        'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
        'offerings' => [['type' => 'product', 'name' => 'Bag', 'price' => '100', 'price_max' => '50', 'currency' => 'PHP']],
    ])->assertSessionHasErrors('offerings.0.price_max');

    $this->assertDatabaseMissing('businesses', ['name' => 'Invalid shop']);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a failed draft item rolls back registration and cleans all new photos', function () {
    $repository = Mockery::mock(app(BusinessRepositoryInterface::class));
    $repository->shouldReceive('getSlugPatternCount')->with('failed-shop')->andReturn(0);
    app()->instance(BusinessRepositoryInterface::class, $repository);
    $realOfferings = app(BusinessOfferingRepositoryInterface::class);
    $offerings = Mockery::mock(BusinessOfferingRepositoryInterface::class);
    $calls = 0;
    $offerings->shouldReceive('create')->twice()->andReturnUsing(function (array $data) use ($realOfferings, &$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('Failed second item');
        }

        return $realOfferings->create($data);
    });
    app()->instance(BusinessOfferingRepositoryInterface::class, $offerings);

    expect(fn () => app(BusinessService::class)->registerBusiness([
        'category_id' => $this->category->id, 'name' => 'Failed Shop',
        'description' => 'A new neighborhood shop.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
        'offerings' => [
            ['type' => 'product', 'name' => 'First', 'photo' => UploadedFile::fake()->image('first.jpg')],
            ['type' => 'product', 'name' => 'Second', 'photo' => UploadedFile::fake()->image('second.jpg')],
        ],
    ], $this->owner->id, UploadedFile::fake()->image('cover.jpg'), null))->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('businesses', ['name' => 'Failed Shop']);
    $this->assertDatabaseMissing('business_offerings', ['name' => 'First']);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
