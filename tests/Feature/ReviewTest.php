<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->other = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->otherOwner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'Review Coffee', 'slug' => 'review-coffee',
        'description' => 'Coffee nearby.', 'address' => 'Sample Street',
        'lat' => 14.6, 'lng' => 121.0, 'cover_photo' => 'covers/sample.jpg', 'hours' => [],
    ]);
    $this->review = Review::create([
        'business_id' => $this->business->id, 'user_id' => $this->consumer->id,
        'rating' => 4, 'body' => 'Friendly staff and good coffee.', 'status' => ReviewStatus::PUBLISHED,
    ]);
});

test('guests see real ratings, review names and replies without private account fields', function () {
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thank you for visiting.']);
    $this->getJson(route('businesses.reviews.index', $this->business->slug))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('summary.count', 1)->assertJsonPath('summary.average', 4)
        ->assertJsonPath('reviews.meta.per_page', 10)
        ->assertJsonPath('reviews.data.0.author', $this->consumer->name)
        ->assertJsonPath('reviews.data.0.reply.body', 'Thank you for visiting.')
        ->assertJsonPath('reviews.data.0.can_edit', false)
        ->assertJsonPath('own_review', null)->assertJsonPath('can_review', false)
        ->assertJsonMissingPath('reviews.data.0.user_id')
        ->assertJsonMissingPath('reviews.data.0.author.email');
    $this->get(route('businesses.show', $this->business->slug))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('feedback.summary.count', 1)->where('feedback.reviews.meta.total', 1));
});

test('consumers can publish once and cannot override ownership or visibility', function () {
    $this->actingAs($this->other)->post(route('consumer.reviews.store', $this->business), [
        'rating' => 5, 'body' => 'A great place to stop for coffee.',
        'user_id' => $this->consumer->id, 'status' => 'hidden', 'business_id' => 999,
    ])->assertRedirect(route('businesses.show', $this->business->slug));
    $this->assertDatabaseHas('reviews', ['business_id' => $this->business->id, 'user_id' => $this->other->id, 'rating' => 5, 'status' => 'published']);
    $this->postJson(route('consumer.reviews.store', $this->business), ['rating' => 2, 'body' => 'A second review is not allowed.'])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    expect(Review::where('user_id', $this->other->id)->count())->toBe(1);
});

test('the database rejects a second review for the same consumer and business', function () {
    expect(fn () => Review::create([
        'business_id' => $this->business->id, 'user_id' => $this->consumer->id,
        'rating' => 1, 'body' => 'Duplicate review.',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('review mutations require a consumer and validate ratings and written content', function () {
    $url = route('consumer.reviews.store', $this->business);
    $this->postJson($url, ['rating' => 5, 'body' => 'Great coffee here.'])->assertUnauthorized();
    $this->actingAs($this->owner)->postJson($url, ['rating' => 5, 'body' => 'Great coffee here.'])->assertForbidden();
    $this->actingAs($this->other)->postJson($url, ['rating' => 6, 'body' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['rating', 'body']);
    $this->postJson($url, ['rating' => 0, 'body' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors(['rating', 'body']);
    $this->postJson($url, ['rating' => 2.5, 'body' => 'A valid written review.'])->assertUnprocessable()->assertJsonValidationErrors('rating');
    $this->postJson($url, ['rating' => 3, 'body' => '          '])->assertUnprocessable()->assertJsonValidationErrors('body');
});

test('only the review author may edit or delete and aggregates follow changes', function () {
    $url = route('consumer.reviews.update', [$this->business, $this->review]);
    $delete = route('consumer.reviews.destroy', [$this->business, $this->review]);
    $this->actingAs($this->other)->putJson($url, ['rating' => 1, 'body' => 'Trying to change another review.'])->assertForbidden();
    $this->deleteJson($delete)->assertForbidden();
    $this->actingAs($this->consumer)->put($url, ['rating' => 2, 'body' => 'An updated account of my visit.'])->assertRedirect();
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertJsonPath('summary.average', 2)->assertJsonPath('own_review.rating', 2)->assertJsonPath('reviews.data.0.can_edit', true);
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'Thanks for the feedback.']);
    $this->delete($delete)->assertRedirect();
    $this->assertDatabaseMissing('reviews', ['id' => $this->review->id]);
    $this->assertDatabaseCount('review_replies', 0);
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertJsonPath('summary.count', 0)->assertJsonPath('summary.average', null);
});

test('hidden reviews and replies never enter public totals and editing does not republish them', function () {
    $this->review->update(['status' => ReviewStatus::HIDDEN]);
    ReviewReply::create(['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'A hidden reply.']);
    $url = route('businesses.reviews.index', $this->business->slug);
    $this->getJson($url)->assertJsonCount(0, 'reviews.data')->assertJsonPath('summary.count', 0)->assertJsonPath('summary.average', null)->assertJsonPath('own_review', null);
    $this->actingAs($this->consumer)->getJson($url)->assertJsonPath('own_review.status', 'hidden');
    $this->put(route('consumer.reviews.update', [$this->business, $this->review]), ['rating' => 5, 'body' => 'Edited but still hidden from public.', 'status' => 'published'])->assertRedirect();
    expect($this->review->fresh()->status)->toBe(ReviewStatus::HIDDEN);
    $this->postJson(route('consumer.reviews.store', $this->business), ['rating' => 5, 'body' => 'Cannot bypass the hidden review.'])->assertUnprocessable();
    $this->actingAs($this->owner)->putJson(route('owner.reviews.reply.update', [$this->business, $this->review]), ['body' => 'Cannot reply while hidden.'])->assertForbidden();
});

test('only the business owner may create edit and remove its one reply', function () {
    $url = route('owner.reviews.reply.update', [$this->business, $this->review]);
    $this->putJson($url, ['body' => 'Thank you.'])->assertUnauthorized();
    $this->actingAs($this->consumer)->putJson($url, ['body' => 'Thank you.'])->assertForbidden();
    $this->actingAs($this->otherOwner)->putJson($url, ['body' => 'Thank you.'])->assertForbidden();
    $this->actingAs($this->owner)->putJson($url, ['body' => ''])->assertUnprocessable();
    $this->put($url, ['body' => 'Thank you for stopping by.'])->assertRedirect();
    $this->put($url, ['body' => 'We hope to see you again.'])->assertRedirect();
    $this->assertDatabaseCount('review_replies', 1);
    $this->assertDatabaseHas('review_replies', ['review_id' => $this->review->id, 'user_id' => $this->owner->id, 'body' => 'We hope to see you again.']);
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertJsonPath('can_reply', true)->assertJsonPath('can_review', false);
    $this->delete(route('owner.reviews.reply.destroy', [$this->business, $this->review]))->assertRedirect();
    $this->assertDatabaseCount('review_replies', 0);
    $this->assertDatabaseHas('reviews', ['id' => $this->review->id]);
});

test('nested routes reject reviews belonging to a different business', function () {
    $otherBusiness = $this->business->replicate();
    $otherBusiness->slug = 'other-review-coffee';
    $otherBusiness->save();
    $this->actingAs($this->consumer)->putJson(route('consumer.reviews.update', [$otherBusiness, $this->review]), ['rating' => 1, 'body' => 'Cannot change through another business.'])->assertNotFound();
    $this->deleteJson(route('consumer.reviews.destroy', [$otherBusiness, $this->review]))->assertNotFound();
    $this->actingAs($this->owner)->putJson(route('owner.reviews.reply.update', [$otherBusiness, $this->review]), ['body' => 'Cannot reply through another business.'])->assertNotFound();
});

test('public reviews paginate and eagerly load related records', function () {
    foreach (range(1, 11) as $number) {
        $user = User::factory()->create(['role' => UserRole::CONSUMER]);
        Review::create(['business_id' => $this->business->id, 'user_id' => $user->id, 'rating' => 5, 'body' => 'A genuine written test review.']);
    }
    $url = route('businesses.reviews.index', $this->business->slug);
    $this->getJson($url)->assertJsonCount(10, 'reviews.data')->assertJsonPath('summary.count', 12)->assertJsonPath('summary.average', 4.9);
    $this->getJson($url.'?page=2')->assertJsonCount(2, 'reviews.data')->assertJsonPath('reviews.meta.current_page', 2);
    $this->getJson($url.'?page=99')->assertJsonPath('reviews.meta.current_page', 2);
    $this->getJson($url.'?page=0')->assertUnprocessable();
    DB::enableQueryLog();
    $reviews = app(ReviewRepositoryInterface::class)->forBusiness($this->business->id);
    foreach ($reviews as $review) {
        $review->author->name;
        $review->reply?->body;
    }
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
    DB::disableQueryLog();
});

test('deleted businesses expose no reviews or mutation endpoints and restore keeps feedback', function () {
    $this->business->delete();
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertNotFound();
    $this->actingAs($this->consumer)->putJson(route('consumer.reviews.update', [$this->business, $this->review]), ['rating' => 5, 'body' => 'Cannot update a deleted business.'])->assertNotFound();
    $this->actingAs($this->owner)->putJson(route('owner.reviews.reply.update', [$this->business, $this->review]), ['body' => 'Cannot reply on a deleted business.'])->assertNotFound();
    $this->business->restore();
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertJsonPath('summary.count', 1);
});
