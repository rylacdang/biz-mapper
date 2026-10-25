<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'Notification Coffee', 'slug' => 'notification-coffee',
        'description' => 'Coffee nearby.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121.0, 'cover_photo' => 'covers/sample.jpg', 'hours' => [],
    ]);
    $this->review = Review::create([
        'business_id' => $this->business->id, 'user_id' => $this->consumer->id,
        'rating' => 4, 'body' => 'Friendly staff and good coffee.', 'status' => ReviewStatus::PUBLISHED,
    ]);
});

test('the owner bell reads existing reviews without a notifications table', function () {
    expect(Schema::hasTable('notifications'))->toBeFalse();
    $this->actingAs($this->owner)->getJson(route('notifications.index'))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'notifications.data')
        ->assertJsonPath('notifications.data.0.id', 'review-'.$this->review->id)
        ->assertJsonPath('notifications.data.0.title', 'New review')
        ->assertJsonPath('notifications.data.0.message', 'Someone reviewed Notification Coffee.')
        ->assertJsonPath('notifications.data.0.read_at', null)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonMissingPath('notifications.data.0.body')
        ->assertJsonMissingPath('notifications.data.0.user_id');
});

test('consumer alerts come from real owner replies and remain private', function () {
    $reply = ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you for visiting.']);
    $this->actingAs($this->consumer)->getJson(route('notifications.index'))
        ->assertOk()->assertJsonCount(1, 'notifications.data')
        ->assertJsonPath('notifications.data.0.id', 'reply-'.$reply->id)
        ->assertJsonPath('notifications.data.0.title', 'Owner replied')
        ->assertJsonPath('unread_count', 1);
    $other = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->actingAs($other)->getJson(route('notifications.index'))
        ->assertJsonCount(0, 'notifications.data')->assertJsonPath('unread_count', 0);
});

test('guests cannot read or update the bell and foreign activity cannot be marked read', function () {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
    $this->putJson(route('notifications.read-all'))->assertUnauthorized();
    $this->putJson(route('notifications.read', 'review-'.$this->review->id))->assertUnauthorized();
    $other = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($other)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data');
    $this->putJson(route('notifications.read', 'review-'.$this->review->id))->assertNotFound();
    $this->actingAs($this->consumer)->putJson(route('notifications.read', 'review-'.$this->review->id))->assertNotFound();
});

test('marking read uses recipient-scoped cache and keeps the first read time', function () {
    $url = route('notifications.read', 'review-'.$this->review->id);
    $this->actingAs($this->owner)->putJson($url)->assertOk()->assertJsonPath('unread_count', 0);
    $firstRead = $this->getJson(route('notifications.index'))->json('notifications.data.0.read_at');
    $this->travel(1)->hour();
    $this->putJson($url)->assertOk()->assertJsonPath('read_at', $firstRead);
    $this->getJson(route('notifications.index'))->assertJsonPath('notifications.data.0.read_at', $firstRead);
    expect(Schema::hasTable('notifications'))->toBeFalse();
});

test('mark all read does not mark a later review or another person reply read', function () {
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you.']);
    $this->actingAs($this->owner)->putJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unread_count', 0);
    $other = User::factory()->create(['role' => UserRole::CONSUMER]);
    Review::create(['business_id' => $this->business->id, 'user_id' => $other->id, 'rating' => 5, 'body' => 'Another review.', 'status' => ReviewStatus::PUBLISHED]);
    $this->getJson(route('notifications.index'))->assertJsonPath('unread_count', 1);
    $this->actingAs($this->consumer)->getJson(route('notifications.index'))->assertJsonPath('unread_count', 1);
});

test('hidden reviews and trashed businesses disappear from both bell feeds', function () {
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you.']);
    $this->review->update(['status' => ReviewStatus::HIDDEN]);
    foreach ([$this->owner, $this->consumer] as $user) {
        $this->actingAs($user)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data')->assertJsonPath('unread_count', 0);
    }
    $this->review->update(['status' => ReviewStatus::PUBLISHED]);
    $this->business->delete();
    foreach ([$this->owner, $this->consumer] as $user) {
        $this->actingAs($user)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data');
    }
    $this->business->restore();
    $this->getJson(route('notifications.index'))->assertJsonCount(1, 'notifications.data');
});

test('deleted reviews or replies disappear without notification cleanup hooks', function () {
    $reply = ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you.']);
    $reply->delete();
    $this->actingAs($this->consumer)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data');
    $this->review->delete();
    $this->actingAs($this->owner)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data');
    $this->putJson(route('notifications.read', 'review-'.$this->review->id))->assertNotFound();
});

test('a reply by someone other than the current business owner is not exposed', function () {
    $other = User::factory()->create(['role' => UserRole::OWNER]);
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $other->id, 'body' => 'Invalid owner.']);
    $this->actingAs($this->consumer)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data');
});

test('edits keep activity identity and do not reset read markers', function () {
    $reply = ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you.']);
    $this->actingAs($this->consumer)->putJson(route('notifications.read', 'reply-'.$reply->id))->assertOk();
    $reply->update(['body' => 'Thank you again.']);
    $this->getJson(route('notifications.index'))->assertJsonCount(1, 'notifications.data')->assertJsonPath('unread_count', 0);
});

test('activity is paginated and query validation preserves the frontend contract', function () {
    for ($i = 0; $i < 11; $i++) {
        Review::create([
            'business_id' => $this->business->id, 'user_id' => User::factory()->create()->id,
            'rating' => 4, 'body' => 'Another published review.', 'status' => ReviewStatus::PUBLISHED,
        ]);
    }
    $this->actingAs($this->owner)->getJson(route('notifications.index'))
        ->assertJsonCount(10, 'notifications.data')->assertJsonPath('notifications.meta.total', 12)
        ->assertJsonPath('notifications.meta.current_page', 1)->assertJsonPath('unread_count', 12);
    $this->getJson(route('notifications.index', ['page' => 2]))->assertJsonCount(2, 'notifications.data');
    $this->getJson(route('notifications.index', ['page' => 0]))->assertUnprocessable();
    $this->getJson(route('notifications.index', ['page' => 'invalid']))->assertUnprocessable();
    $this->get(route('businesses.show', ['slug' => $this->business->slug, 'review' => $this->review->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feedback.reviews.meta.current_page', 2)
            ->where('feedback.reviews.data.1.id', $this->review->id));
});

test('activity links use the current business slug', function () {
    $this->business->update(['slug' => 'renamed-coffee']);
    $this->actingAs($this->owner)->getJson(route('notifications.index'))
        ->assertJsonPath('notifications.data.0.url', '/businesses/renamed-coffee?review='.$this->review->id.'#review-'.$this->review->id);
});

test('cache reset clears only read markers and activity can still be listed', function () {
    $this->actingAs($this->owner)->putJson(route('notifications.read-all'))->assertJsonPath('unread_count', 0);
    Cache::flush();
    $this->getJson(route('notifications.index'))->assertJsonCount(1, 'notifications.data')->assertJsonPath('unread_count', 1);
});

test('deleting an author removes their source activity naturally', function () {
    $this->consumer->delete();
    $this->actingAs($this->owner)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications.data')->assertJsonPath('unread_count', 0);
});

test('loading the bell batches business lookups instead of querying for each row', function () {
    for ($i = 0; $i < 9; $i++) {
        Review::create(['business_id' => $this->business->id, 'user_id' => User::factory()->create()->id, 'rating' => 4, 'body' => 'Another review.', 'status' => ReviewStatus::PUBLISHED]);
    }
    DB::enableQueryLog();
    app(NotificationRepositoryInterface::class)->forUser($this->owner);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(count($queries))->toBeLessThanOrEqual(3);
});
