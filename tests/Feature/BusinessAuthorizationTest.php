<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Policies\BasePolicy;
use App\Policies\BusinessPolicy;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create([
        'name' => 'Coffee',
        'icon' => 'CakeIcon',
        'color' => '#2563eb',
    ]);

    $this->business = Business::create([
        'user_id' => $this->owner->id,
        'category_id' => $category->id,
        'name' => 'Local Coffee',
        'slug' => 'local-coffee',
        'description' => 'A neighborhood coffee shop.',
        'address' => 'Sample Street',
        'lat' => 14.6,
        'lng' => 121.0,
        'cover_photo' => 'covers/sample.jpg',
        'hours' => [],
    ]);
});

test('owners can create a listing with uploads and manually chosen coordinates', function () {
    Storage::fake('public');
    // SQLite has no RLIKE operator; keep this test focused on the upload workflow.
    $repository = Mockery::mock(app(BusinessRepositoryInterface::class));
    $repository->shouldReceive('getSlugPatternCount')->with('new-coffee')->andReturn(0);
    app()->instance(BusinessRepositoryInterface::class, $repository);

    $this->actingAs($this->owner)->post(route('owner.businesses.store'), [
        'category_id' => $this->business->category_id,
        'name' => 'New Coffee',
        'description' => 'A newly created neighborhood coffee shop.',
        'address' => 'Manual Pin Street',
        'lat' => 14.612345,
        'lng' => 121.012345,
        'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
        'logo' => UploadedFile::fake()->image('logo.png'),
        'hours' => ['monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('explorer.index'));

    $created = Business::where('slug', 'new-coffee')->firstOrFail();
    expect((float) $created->lat)->toBe(14.612345);
    expect((float) $created->lng)->toBe(121.012345);
    expect($created->user_id)->toBe($this->owner->id);
    Storage::disk('public')->assertExists([$created->cover_photo, $created->logo]);
});

test('owners can update uploads using post method spoofing', function () {
    Storage::fake('public');
    $this->actingAs($this->owner)->post(route('owner.businesses.update', $this->business), [
        '_method' => 'PUT',
        'cover_photo' => UploadedFile::fake()->image('replacement.jpg'),
        'lat' => 14.612345,
        'lng' => 121.012345,
    ])->assertSessionHasNoErrors()->assertRedirect(route('explorer.index'));

    $updated = $this->business->fresh();
    expect((float) $updated->lat)->toBe(14.612345);
    expect((float) $updated->lng)->toBe(121.012345);
    expect($updated->cover_photo)->not->toBe('covers/sample.jpg');
    Storage::disk('public')->assertExists($updated->cover_photo);
});

dataset('business actions', [
    'update' => ['put', 'owner.businesses.update'],
    'delete' => ['delete', 'owner.businesses.destroy'],
    'restore' => ['post', 'owner.businesses.restore'],
]);

test('business declares its policy on the model', function () {
    $attribute = (new ReflectionClass(Business::class))->getAttributes(UsePolicy::class)[0];

    expect($attribute->getArguments())->toBe([BusinessPolicy::class]);
    expect(Gate::getPolicyFor($this->business))
        ->toBeInstanceOf(BusinessPolicy::class)
        ->toBeInstanceOf(BasePolicy::class);
});

test('owners can modify their own businesses', function (string $method, string $routeName) {
    if ($routeName === 'owner.businesses.restore') {
        $this->business->delete();
    }

    $this->actingAs($this->owner)
        ->{$method}(route($routeName, $this->business->id), ['phone' => '09171234567'])
        ->assertRedirect(route('explorer.index'));

    $business = Business::withTrashed()->findOrFail($this->business->id);

    if ($routeName === 'owner.businesses.update') {
        expect($business->phone)->toBe('09171234567');
    } else {
        expect($business->trashed())->toBe($routeName === 'owner.businesses.destroy');
    }
})->with('business actions');

test('other owners cannot modify a business', function (string $method, string $routeName) {
    if ($routeName === 'owner.businesses.restore') {
        $this->business->delete();
    }

    $before = $this->business->fresh()->getAttributes();
    $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($otherOwner)
        ->{$method}(route($routeName, $this->business->id), ['phone' => '09171234567'])
        ->assertForbidden();

    expect(Business::withTrashed()->findOrFail($this->business->id)->getAttributes())
        ->toBe($before);
})->with('business actions');

test('consumers cannot modify owner listings', function (string $method, string $routeName) {
    if ($routeName === 'owner.businesses.restore') {
        $this->business->delete();
    }

    $consumer = User::factory()->create(['role' => UserRole::CONSUMER]);

    $this->actingAs($consumer)
        ->{$method}(route($routeName, $this->business->id), ['phone' => '09171234567'])
        ->assertForbidden();
})->with('business actions');

test('restore returns not found for a missing or non-trashed listing', function (bool $missing) {
    $id = $this->business->id;

    if ($missing) {
        $this->business->forceDelete();
    }

    $this->actingAs($this->owner)
        ->post(route('owner.businesses.restore', $id))
        ->assertNotFound();
})->with([true, false]);
