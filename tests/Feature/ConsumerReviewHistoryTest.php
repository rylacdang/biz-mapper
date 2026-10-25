<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'History Coffee', 'slug' => 'history-coffee',
        'description' => 'A neighborhood coffee shop.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121, 'cover_photo' => 'covers/sample.jpg', 'hours' => [],
    ]);
    $this->review = Review::create([
        'business_id' => $this->business->id, 'user_id' => $this->consumer->id,
        'rating' => 4, 'body' => 'Friendly service and good coffee.', 'status' => ReviewStatus::PUBLISHED,
    ]);
});

test('only consumers may access their personal review history', function () {
    $this->getJson(route('consumer.reviews.index'))->assertUnauthorized();
    $this->actingAs($this->owner)->getJson(route('consumer.reviews.index'))->assertForbidden();
    expect($this->owner->can('viewAny', Review::class))->toBeFalse();
    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index'))->assertOk();
});

test('history contains only the authenticated authors feedback and safe business details', function () {
    $other = User::factory()->create(['role' => UserRole::CONSUMER]);
    Review::create(['business_id' => $this->business->id, 'user_id' => $other->id, 'rating' => 1, 'body' => 'Another account private history.', 'status' => ReviewStatus::HIDDEN]);
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you for visiting.']);

    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index', ['user_id' => $other->id]))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->review->id)
        ->assertJsonPath('data.0.business.name', 'History Coffee')
        ->assertJsonPath('data.0.rating', 4)
        ->assertJsonPath('data.0.body', 'Friendly service and good coffee.')
        ->assertJsonPath('data.0.reply.body', 'Thank you for visiting.')
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.author')
        ->assertJsonMissingPath('data.0.business.user_id')
        ->assertJsonMissingPath('data.0.business.deleted_at')
        ->assertJsonMissingPath('data.0.reply.user_id');
    $this->actingAs($other)->getJson(route('consumer.reviews.index'))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'hidden');
});

test('hidden reviews remain in personal history and link to the profile review controls', function () {
    $this->review->update(['status' => ReviewStatus::HIDDEN]);
    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index'))
        ->assertJsonPath('data.0.status', 'hidden')
        ->assertJsonPath('data.0.url', '/businesses/history-coffee?review='.$this->review->id.'#reviews-title');
    $this->get(route('businesses.show', ['slug' => $this->business->slug, 'review' => $this->review->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feedback.own_review.id', $this->review->id)
            ->where('feedback.can_review', true)
            ->where('feedback.reviews.meta.total', 0));
});

test('unavailable businesses keep personal history without exposing a broken link', function () {
    $this->business->delete();
    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.business.name', 'History Coffee')
        ->assertJsonPath('data.0.url', null);
    $this->business->restore();
    $this->business->update(['slug' => 'renamed-history-coffee']);
    $this->getJson(route('consumer.reviews.index'))
        ->assertJsonPath('data.0.url', '/businesses/renamed-history-coffee?review='.$this->review->id.'#review-'.$this->review->id);
});

test('history is paginated newest first and invalid pages are rejected', function () {
    for ($i = 0; $i < 11; $i++) {
        $business = $this->business->replicate();
        $business->slug = 'history-place-'.$i;
        $business->save();
        Review::create(['business_id' => $business->id, 'user_id' => $this->consumer->id, 'rating' => 5, 'body' => 'Another place I visited.', 'status' => ReviewStatus::PUBLISHED]);
    }
    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index'))
        ->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 12)->assertJsonPath('meta.per_page', 10);
    $this->getJson(route('consumer.reviews.index', ['page' => 2]))
        ->assertJsonCount(2, 'data')->assertJsonPath('data.1.id', $this->review->id);
    $this->getJson(route('consumer.reviews.index', ['page' => 999]))->assertJsonPath('meta.current_page', 2);
    $this->getJson(route('consumer.reviews.index', ['page' => 0]))->assertUnprocessable();
    $this->getJson(route('consumer.reviews.index', ['page' => 'bad']))->assertUnprocessable();
});

test('an empty history stays usable and edits and deletions appear on the next fetch', function () {
    $other = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->actingAs($other)->getJson(route('consumer.reviews.index'))->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    $this->review->update(['rating' => 2, 'body' => 'My updated feedback.']);
    $this->actingAs($this->consumer)->getJson(route('consumer.reviews.index'))
        ->assertJsonPath('data.0.rating', 2)->assertJsonPath('data.0.body', 'My updated feedback.');
    $this->review->delete();
    $this->getJson(route('consumer.reviews.index'))->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
});

test('business details and owner replies are loaded in batches for a history page', function () {
    for ($i = 0; $i < 9; $i++) {
        $business = $this->business->replicate();
        $business->slug = 'batched-history-'.$i;
        $business->save();
        Review::create(['business_id' => $business->id, 'user_id' => $this->consumer->id, 'rating' => 3, 'body' => 'My feedback.', 'status' => ReviewStatus::PUBLISHED]);
    }

    DB::enableQueryLog();
    $reviews = app(ReviewRepositoryInterface::class)->historyForUser($this->consumer->id);
    foreach ($reviews as $review) {
        $review->business?->name;
        $review->reply?->body;
    }
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($reviews->total())->toBe(10)->and(count($queries))->toBeLessThanOrEqual(4);
});
