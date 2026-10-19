<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Services\BusinessOfferingService;
use App\Services\BusinessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('public');
    config(['inertia.ssr.enabled' => false]);
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->listing = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'Gallery Coffee', 'slug' => 'gallery-coffee',
        'description' => 'Coffee and pastries nearby.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121.0, 'cover_photo' => 'covers/original.jpg',
        'gallery_photos' => ['business-gallery/first.jpg', 'business-gallery/second.jpg'], 'hours' => [],
    ]);
    Storage::disk('public')->put('covers/original.jpg', 'cover');
    Storage::disk('public')->put('business-gallery/first.jpg', 'first');
    Storage::disk('public')->put('business-gallery/second.jpg', 'second');
});

test('owners can upload an ordered gallery during registration', function () {
    // SQLite has no RLIKE operator; leave slug generation outside this upload test.
    $repository = Mockery::mock(app(BusinessRepositoryInterface::class));
    $repository->shouldReceive('getSlugPatternCount')->with('new-gallery')->andReturn(0);
    app()->instance(BusinessRepositoryInterface::class, $repository);

    $this->actingAs($this->owner)->post(route('owner.businesses.store'), [
        'category_id' => $this->listing->category_id, 'name' => 'New Gallery',
        'description' => 'A new business with gallery photos.', 'address' => 'Sample Street',
        'lat' => 14.612345, 'lng' => 121.012345,
        'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
        'gallery' => [['file' => UploadedFile::fake()->image('first.jpg')], ['file' => UploadedFile::fake()->image('second.jpg')]],
        'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('explorer.index'));

    $created = Business::where('slug', 'new-gallery')->firstOrFail();
    expect($created->gallery_photos)->toHaveCount(2);
    expect((float) $created->lat)->toBe(14.612345);
    Storage::disk('public')->assertExists($created->gallery_photos);
});

test('owners can reorder retain remove and upload gallery photos using method spoofing', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.update', $this->listing), [
        '_method' => 'PUT', 'replace_gallery' => true,
        'gallery' => [['path' => 'business-gallery/second.jpg'], ['file' => UploadedFile::fake()->image('new.jpg')]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('explorer.index'));

    $photos = $this->listing->fresh()->gallery_photos;
    expect($photos)->toHaveCount(2);
    expect($photos[0])->toBe('business-gallery/second.jpg');
    Storage::disk('public')->assertExists($photos);
    Storage::disk('public')->assertMissing('business-gallery/first.jpg');
    Storage::disk('public')->assertExists('covers/original.jpg');
});

test('multipart updates can remove the entire gallery without changing the cover', function () {
    $this->actingAs($this->owner)->post(route('owner.businesses.update', $this->listing), [
        '_method' => 'PUT', 'replace_gallery' => '1',
    ])->assertSessionHasNoErrors();
    expect($this->listing->fresh()->gallery_photos)->toBe([]);
    Storage::disk('public')->assertMissing(['business-gallery/first.jpg', 'business-gallery/second.jpg']);
    Storage::disk('public')->assertExists('covers/original.jpg');
});

test('updates that omit the gallery retain saved photos', function () {
    $this->actingAs($this->owner)->put(route('owner.businesses.update', $this->listing), ['phone' => '09171234567'])
        ->assertSessionHasNoErrors();
    expect($this->listing->fresh()->gallery_photos)->toBe($this->listing->gallery_photos);
    Storage::disk('public')->assertExists($this->listing->gallery_photos);
});

test('owners cannot attach another listings file paths or empty gallery entries', function () {
    $this->actingAs($this->owner)->put(route('owner.businesses.update', $this->listing), [
        'gallery' => [['path' => 'business-gallery/someone-else.jpg'], ['path' => null, 'file' => null]],
    ])->assertSessionHasErrors(['gallery.0.path', 'gallery.1.file']);
    expect($this->listing->fresh()->gallery_photos)->toBe($this->listing->gallery_photos);
});

test('gallery uploads reject unsupported images and oversized files', function () {
    $this->actingAs($this->owner)->put(route('owner.businesses.update', $this->listing), [
        'gallery' => [['file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')], ['file' => UploadedFile::fake()->image('large.jpg')->size(5121)]],
    ])->assertSessionHasErrors(['gallery.0.file', 'gallery.1.file']);
});

test('gallery uploads are limited to five photos', function () {
    $this->actingAs($this->owner)->put(route('owner.businesses.update', $this->listing), [
        'gallery' => array_map(fn ($index) => ['file' => UploadedFile::fake()->image('photo-'.$index.'.jpg')], range(1, 6)),
    ])->assertSessionHasErrors('gallery');
});

test('other owners cannot edit gallery photos', function () {
    $other = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($other)->put(route('owner.businesses.update', $this->listing), ['replace_gallery' => true])->assertForbidden();
    Storage::disk('public')->assertExists($this->listing->gallery_photos);
});

test('failed updates clean new uploads and preserve original files', function () {
    $repository = Mockery::mock(BusinessRepositoryInterface::class);
    $repository->shouldReceive('update')->once()->andThrow(new RuntimeException('Save failed'));
    $service = new BusinessService($repository, app(BusinessOfferingService::class));

    expect(fn () => $service->updateBusiness($this->listing, [
        'gallery' => [['file' => UploadedFile::fake()->image('new.jpg')]],
    ], UploadedFile::fake()->image('new-cover.jpg'), null))->toThrow(RuntimeException::class);

    Storage::disk('public')->assertExists([...$this->listing->gallery_photos, 'covers/original.jpg']);
    expect(Storage::disk('public')->allFiles())->toHaveCount(3);
});

test('public profiles expose the ordered gallery without owner fields', function () {
    $this->get(route('businesses.show', $this->listing->slug))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('businesses/Show')
            ->where('business.gallery_photos', $this->listing->gallery_photos)
            ->missing('business.user_id'));
});

test('soft deleting and restoring listings preserves gallery files', function () {
    $this->actingAs($this->owner)->delete(route('owner.businesses.destroy', $this->listing))->assertRedirect();
    Storage::disk('public')->assertExists($this->listing->gallery_photos);
    $this->actingAs($this->owner)->post(route('owner.businesses.restore', $this->listing->id))->assertRedirect();
    expect($this->listing->fresh()->gallery_photos)->toBe($this->listing->gallery_photos);
});
