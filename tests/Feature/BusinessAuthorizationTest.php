<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Policies\BasePolicy;
use App\Policies\BusinessPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Support\Facades\Gate;

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

dataset('business actions', [
    'update' => ['put', 'owner.business.update'],
    'delete' => ['delete', 'owner.business.destroy'],
    'restore' => ['post', 'owner.business.restore'],
]);

test('business declares its policy on the model', function () {
    $attribute = (new ReflectionClass(Business::class))->getAttributes(UsePolicy::class)[0];

    expect($attribute->getArguments())->toBe([BusinessPolicy::class]);
    expect(Gate::getPolicyFor($this->business))
        ->toBeInstanceOf(BusinessPolicy::class)
        ->toBeInstanceOf(BasePolicy::class);
});

test('owners can modify their own businesses', function (string $method, string $routeName) {
    if ($routeName === 'owner.business.restore') {
        $this->business->delete();
    }

    $this->actingAs($this->owner)
        ->{$method}(route($routeName, $this->business->id), ['phone' => '09171234567'])
        ->assertRedirect(route('owner.dashboard'));

    $business = Business::withTrashed()->findOrFail($this->business->id);

    if ($routeName === 'owner.business.update') {
        expect($business->phone)->toBe('09171234567');
    } else {
        expect($business->trashed())->toBe($routeName === 'owner.business.destroy');
    }
})->with('business actions');

test('other owners cannot modify a business', function (string $method, string $routeName) {
    if ($routeName === 'owner.business.restore') {
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
    if ($routeName === 'owner.business.restore') {
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
        ->post(route('owner.business.restore', $id))
        ->assertNotFound();
})->with([true, false]);
