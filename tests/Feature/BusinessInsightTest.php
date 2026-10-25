<?php

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $category->id,
        'name' => 'Insight Coffee', 'slug' => 'insight-coffee', 'description' => 'A nearby cafe.',
        'address' => 'Sample Street', 'lat' => 14.6, 'lng' => 121.0,
        'cover_photo' => 'covers/coffee.jpg', 'hours' => [],
    ]);
    $this->profile = route('businesses.show', $this->business->slug);
    $this->withHeader('User-Agent', 'Mozilla/5.0 BizMapperTestBrowser');
    $this->freezeTime();
});

function insightReview(Business $business, int $rating = 4, array $extra = []): Review
{
    return Review::create(array_merge([
        'business_id' => $business->id,
        'user_id' => User::factory()->create(['role' => UserRole::CONSUMER])->id,
        'rating' => $rating, 'body' => 'Friendly staff and convenient location.',
        'status' => ReviewStatus::PUBLISHED,
    ], $extra));
}

test('guest profile visits count once per session and preserve listing edit time', function () {
    $edited = $this->business->updated_at;
    $this->withSession(['profile_view_visitor' => '11111111-1111-4111-8111-111111111111']);
    $this->travel(1)->minutes();
    $this->get($this->profile)->assertOk();
    $this->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(1)
        ->and($this->business->fresh()->updated_at->equalTo($edited))->toBeTrue();
});

test('a different guest session counts and the same session counts again after a day', function () {
    $this->withSession(['profile_view_visitor' => '11111111-1111-4111-8111-111111111111'])->get($this->profile);
    $this->withSession(['profile_view_visitor' => '22222222-2222-4222-8222-222222222222'])->get($this->profile);
    expect($this->business->fresh()->view_count)->toBe(2);
    $this->travel(24)->hours();
    $this->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(3);
});

test('signed in accounts deduplicate across sessions and owners never count their own visits', function () {
    $this->actingAs($this->owner)->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(0);
    $viewer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->actingAs($viewer)->get($this->profile);
    $this->withSession(['profile_view_visitor' => '33333333-3333-4333-8333-333333333333'])->get($this->profile);
    expect($this->business->fresh()->view_count)->toBe(1);
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]))->get($this->profile);
    expect($this->business->fresh()->view_count)->toBe(2);
});

test('json reads head requests prefetches partial reloads and known crawlers do not count', function (array $headers, string $method) {
    if (isset($headers['X-Inertia'])) {
        $this->get(route('explorer.index'))->assertOk();
        $headers['X-Inertia-Version'] = Inertia::getVersion();
    }

    $this->call($method, $this->profile, server: $this->transformHeadersToServerVars($headers))->assertSuccessful();
    expect($this->business->fresh()->view_count)->toBe(0);
})->with([
    [['Accept' => 'application/json'], 'GET'],
    [[], 'HEAD'],
    [['Purpose' => 'prefetch'], 'GET'],
    [['Sec-Purpose' => 'prefetch;prerender'], 'GET'],
    [['X-Inertia-Prefetch' => 'true'], 'GET'],
    [['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'businesses/Show', 'X-Inertia-Partial-Data' => 'feedback'], 'GET'],
    [['User-Agent' => 'Googlebot/2.1'], 'GET'],
    [['User-Agent' => 'facebookexternalhit/1.1'], 'GET'],
    [['User-Agent' => 'HeadlessChrome/130.0'], 'GET'],
    [['User-Agent' => ''], 'GET'],
]);

test('normal inertia visits count but search and other public endpoints never do', function () {
    $this->get(route('explorer.index'))->assertOk();
    $this->get($this->profile, ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])->assertOk();
    expect($this->business->fresh()->view_count)->toBe(1);
    $this->getJson(route('businesses.review-summary.show', $this->business->slug))->assertOk();
    $this->getJson(route('businesses.reviews.index', $this->business->slug))->assertOk();
    $this->getJson($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(1);
});

test('deleted listings are not counted and restored listings retain their totals', function () {
    $this->get($this->profile);
    $this->business->delete();
    $this->get($this->profile)->assertNotFound();
    $this->business->restore();
    $this->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(1);
    $this->business->forceDelete();
});

test('view counters increment atomically without updating listing edit times', function () {
    $repo = app(BusinessRepositoryInterface::class);
    $edited = $this->business->updated_at;
    $this->travel(1)->minutes();
    expect($repo->incrementProfileViews($this->business->id))->toBeTrue()
        ->and($repo->incrementProfileViews($this->business->id))->toBeTrue()
        ->and($this->business->fresh()->view_count)->toBe(2)
        ->and($this->business->fresh()->updated_at->equalTo($edited))->toBeTrue();
});

test('clearing the cache allows a fresh visit to count again', function () {
    $this->get($this->profile)->assertOk();
    Cache::flush();
    $this->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(2);
});

test('a cache failure cannot stop the public profile from loading', function () {
    Log::spy();
    Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('private cache details'));
    $this->get($this->profile)->assertOk();
    expect($this->business->fresh()->view_count)->toBe(0);
    Log::shouldHaveReceived('warning')->with('Profile view tracking unavailable.', [
        'business_id' => $this->business->id, 'category' => 'RuntimeException',
    ])->once();
});

test('counter failures remain isolated from ordinary profile reads', function () {
    Log::spy();
    $repo = $this->mock(BusinessRepositoryInterface::class);
    $repo->shouldReceive('findBySlugWithCategoryOrFail')->with($this->business->slug)->once()->andReturn($this->business);
    $repo->shouldReceive('incrementProfileViews')->with($this->business->id)->once()
        ->andThrow(new RuntimeException('private database details'));
    $this->get($this->profile)->assertOk();
    Log::shouldHaveReceived('warning')->once();
});

test('owner insights contain only fresh published aggregates for each listing', function () {
    $old = insightReview($this->business, 1);
    $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();
    foreach ([3, 4, 5] as $rating) {
        insightReview($this->business, $rating);
    }
    insightReview($this->business, 1, ['status' => ReviewStatus::HIDDEN, 'body' => 'Hidden feedback']);
    Business::whereKey($this->business->id)->update(['view_count' => 12]);
    $other = $this->business->replicate();
    $other->slug = 'second-insight-cafe';
    $other->save();
    for ($i = 0; $i < 4; $i++) {
        insightReview($other);
    }

    $this->actingAs($this->owner)->get(route('explorer.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("ownerInsights.{$this->business->id}.profile_views", 12)
            ->where("ownerInsights.{$this->business->id}.review_count", 4)
            ->where("ownerInsights.{$this->business->id}.average_rating", 3.3)
            ->missing("ownerInsights.{$this->business->id}.recent_reviews")
            ->missing("ownerInsights.{$this->business->id}.recent_review_count")
            ->where("ownerInsights.{$other->id}.review_count", 4));
    $this->assertDatabaseHas('reviews', ['id' => $old->id]);
});

test('hidden and removed reviews update insights without stale listing cache values', function () {
    $review = insightReview($this->business, 5);
    $url = route('explorer.index');
    $this->actingAs($this->owner)->get($url)
        ->assertInertia(fn (AssertableInertia $page) => $page->where("ownerInsights.{$this->business->id}.review_count", 1));
    $review->update(['status' => ReviewStatus::HIDDEN]);
    $this->get($url)->assertInertia(fn (AssertableInertia $page) => $page
        ->where("ownerInsights.{$this->business->id}.review_count", 0)
        ->where("ownerInsights.{$this->business->id}.average_rating", null));
    $review->delete();
    $this->getJson($this->profile)->assertJsonMissingPath('data.view_count')->assertJsonMissingPath('data.ownerInsights');
});

test('insights exclude other owners and trash and never reach guests or consumers', function () {
    $this->business->delete();
    $this->actingAs($this->owner)->get(route('explorer.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('ownerInsights', 0));
    $this->business->restore();
    $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($otherOwner)->get(route('explorer.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('ownerInsights', 0));
    $this->actingAs(User::factory()->create(['role' => UserRole::CONSUMER]))
        ->get(route('explorer.index'))->assertInertia(fn (AssertableInertia $page) => $page->missing('ownerInsights'));
    auth()->logout();
    $this->get(route('explorer.index'), [
        'X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'explorer/Index', 'X-Inertia-Partial-Data' => 'ownerInsights',
    ])->assertJsonMissingPath('props.ownerInsights');
});

test('owners can refresh only their insights through a partial reload', function () {
    $this->actingAs($this->owner)->get(route('explorer.index'))->assertOk();
    $this->get(route('explorer.index'), [
        'X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'explorer/Index', 'X-Inertia-Partial-Data' => 'ownerInsights',
    ])->assertOk()->assertJsonPath("props.ownerInsights.{$this->business->id}.profile_views", 0)
        ->assertJsonMissingPath('props.myBusinesses')->assertJsonMissingPath('props.trashBusinesses');
});
