<?php

use App\Enums\ReviewStatus;
use App\Enums\ReviewSummaryStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Caching\BusinessCacheRepository;
use App\Repositories\Caching\CategoryCacheRepository;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\ReviewSummaryRepositoryInterface;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    Queue::fake();
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->consumer = User::factory()->create(['role' => UserRole::CONSUMER]);
    $this->category = Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#2563eb']);
    $this->business = Business::create([
        'user_id' => $this->owner->id, 'category_id' => $this->category->id,
        'name' => 'Cached Cafe', 'slug' => 'cached-cafe', 'description' => 'Coffee nearby.',
        'cover_photo' => 'covers/sample.jpg', 'address' => 'Sample Street', 'lat' => 14.6, 'lng' => 121.0, 'hours' => [],
    ]);
});

test('every repository binding wraps the matching Eloquent repository', function () {
    $names = ['Business', 'Category', 'BusinessOffering', 'SavedBusiness', 'PlaceCollection', 'Review', 'ReviewSummary', 'Notification'];

    foreach ($names as $name) {
        $repository = app('App\\Repositories\\Contracts\\'.$name.'RepositoryInterface');
        expect($repository)->toBeInstanceOf('App\\Repositories\\Caching\\'.$name.'CacheRepository');
        $inner = (new ReflectionProperty($repository, 'eloquentRepo'))->getValue($repository);
        expect($inner)->toBeInstanceOf('App\\Repositories\\Eloquents\\'.$name.'Repository');
    }
});

test('cache hits skip the database and returned models cannot contaminate later responses', function () {
    $repository = app(BusinessRepositoryInterface::class);
    $first = $repository->findBySlugWithCategoryOrFail('cached-cafe');
    $first->setAttribute('is_saved', true);
    $first->category->name = 'Changed only in this response';
    DB::enableQueryLog();
    DB::flushQueryLog();
    $second = $repository->findBySlugWithCategoryOrFail('cached-cafe');

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($second->getAttribute('is_saved'))->toBeNull()
        ->and($second->category->name)->toBe('Coffee');
});

test('direct category writes invalidate embedded business category data', function () {
    $repository = app(BusinessRepositoryInterface::class);
    expect($repository->findBySlugWithCategoryOrFail('cached-cafe')->category->name)->toBe('Coffee');
    $this->category->update(['name' => 'Cafe']);
    expect($repository->findBySlugWithCategoryOrFail('cached-cafe')->category->name)->toBe('Cafe');
});

test('rollback keeps committed cache data while transaction reads see their own writes', function () {
    $repository = app(CategoryRepositoryInterface::class);
    expect($repository->find($this->category->id)->name)->toBe('Coffee');
    DB::beginTransaction();
    $repository->update($this->category->id, ['name' => 'Uncommitted']);
    expect($repository->find($this->category->id)->name)->toBe('Uncommitted');
    DB::rollBack();
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect($repository->find($this->category->id)->name)->toBe('Coffee')
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::transaction(fn () => $repository->update($this->category->id, ['name' => 'Committed']));
    expect($repository->find($this->category->id)->name)->toBe('Committed');
});

test('an invalidated in-flight cache miss cannot republish obsolete data', function () {
    $inner = Mockery::mock(CategoryRepositoryInterface::class);
    $repository = new CategoryCacheRepository($inner);
    $inner->shouldReceive('all')->once()->andReturnUsing(function () use ($repository) {
        $repository->invalidate();

        return new Collection([new Category(['name' => 'Old'])]);
    });
    $inner->shouldReceive('all')->once()->andReturn(new Collection([new Category(['name' => 'New'])]));

    expect($repository->all()->first()->name)->toBe('Old')
        ->and($repository->all()->first()->name)->toBe('New')
        ->and($repository->all()->first()->name)->toBe('New');
});

test('nearby cache keys preserve coordinates radius filters page and current URL', function () {
    $inner = Mockery::mock(BusinessRepositoryInterface::class);
    $repository = new BusinessCacheRepository($inner);
    $cases = [
        [14.600001, 121.0, 0.5, null, null, 1],
        [14.600002, 121.0, 0.5, null, null, 1],
        [14.600001, 121.0, 1.0, null, null, 1],
        [14.600001, 121.0, 0.5, 'coffee', null, 1],
        [14.600001, 121.0, 0.5, null, ['Coffee'], 1],
        [14.600001, 121.0, 0.5, null, null, 2],
    ];

    foreach ($cases as $index => $arguments) {
        $inner->shouldReceive('searchByRadius')->with(...$arguments)->once()
            ->andReturn(new LengthAwarePaginator([$index], 40, 20, $arguments[5]));
        expect($repository->searchByRadius(...$arguments)->items())->toBe([$index]);
    }

    Paginator::currentPathResolver(fn () => 'https://example.test/businesses/search');
    expect($repository->searchByRadius(...$cases[0])->path())->toBe('https://example.test/businesses/search');
});

test('business deletion restoration and account cascades evict saved and collection reads', function () {
    $saves = app(SavedBusinessRepositoryInterface::class);
    $collections = app(PlaceCollectionRepositoryInterface::class);
    $saves->saveForUser($this->consumer->id, $this->business->id);
    $saved = $saves->query()->first();
    $collection = $collections->create(['user_id' => $this->consumer->id, 'name' => 'Coffee stops']);
    $collections->addPlace($collection->id, $saved->id);

    expect($saves->forUser($this->consumer->id)->total())->toBe(1)
        ->and($collections->places($collection)->total())->toBe(1);
    $this->business->delete();
    expect($saves->forUser($this->consumer->id)->total())->toBe(0)
        ->and($collections->places($collection)->total())->toBe(0);
    app(BusinessRepositoryInterface::class)->restoreBusiness($this->business->id);
    expect($saves->forUser($this->consumer->id)->total())->toBe(1);
    $this->owner->delete();
    expect($saves->forUser($this->consumer->id)->total())->toBe(0);
});

test('removing a saved place evicts collection membership and other users remain isolated', function () {
    $saves = app(SavedBusinessRepositoryInterface::class);
    $collections = app(PlaceCollectionRepositoryInterface::class);
    $saves->saveForUser($this->consumer->id, $this->business->id);
    $saved = $saves->query()->first();
    $collection = $collections->create(['user_id' => $this->consumer->id, 'name' => 'Coffee stops']);
    $collections->addPlace($collection->id, $saved->id);
    expect($collections->places($collection)->total())->toBe(1)
        ->and($saves->forUser($this->owner->id)->total())->toBe(0);
    $saves->removeForUser($this->consumer->id, $this->business->id);
    expect($collections->places($collection)->total())->toBe(0);
});

test('review visibility and replies evict cached reviews and bells without erasing read markers', function () {
    $review = Review::create([
        'business_id' => $this->business->id, 'user_id' => $this->consumer->id,
        'rating' => 4, 'body' => 'Good coffee.', 'status' => ReviewStatus::PUBLISHED,
    ]);
    $reviews = app(ReviewRepositoryInterface::class);
    $bell = app(NotificationRepositoryInterface::class);
    expect($reviews->summary($this->business->id)['count'])->toBe(1)
        ->and($bell->unreadCount($this->owner))->toBe(1);
    $bell->markRead($this->owner, 'review-'.$review->id);
    expect($bell->unreadCount($this->owner))->toBe(0);
    $reviews->saveReply($review->id, $this->owner->id, 'Thank you.');
    expect($reviews->forBusiness($this->business->id)->first()->reply->body)->toBe('Thank you.')
        ->and($bell->unreadCount($this->consumer))->toBe(1);
    $reviews->deleteReply($review->id);
    expect($bell->unreadCount($this->consumer))->toBe(0);
    $review->update(['status' => ReviewStatus::HIDDEN]);
    expect($reviews->summary($this->business->id)['count'])->toBe(0)
        ->and($bell->forUser($this->owner)->total())->toBe(0);
    $review->update(['status' => ReviewStatus::PUBLISHED]);
    expect($bell->unreadCount($this->owner))->toBe(0)
        ->and(Cache::get('bell:read:owner:'.$this->owner->id)['ids'])->toHaveKey($review->id);
});

test('expired listing cache refreshes without caching owner metrics or slug availability', function () {
    $inner = Mockery::mock(BusinessRepositoryInterface::class);
    $repository = new BusinessCacheRepository($inner);
    $inner->shouldReceive('getAllWithCategory')->twice()->andReturn(new Collection);
    $inner->shouldReceive('getOwnerInsights')->with(1, [1])->twice()->andReturn(new Collection);
    $inner->shouldReceive('getSlugPatternCount')->with('cafe')->twice()->andReturn(1, 2);
    $repository->getAllWithCategory();
    $repository->getAllWithCategory();
    $this->travel(61)->seconds();
    $repository->getAllWithCategory();
    $repository->getOwnerInsights(1, [1]);
    $repository->getOwnerInsights(1, [1]);
    expect($repository->getSlugPatternCount('cafe'))->toBe(1)
        ->and($repository->getSlugPatternCount('cafe'))->toBe(2);
});

test('catalog pages separate pagination and sort order then evict on item writes', function () {
    $repository = app(BusinessOfferingRepositoryInterface::class);
    $first = $repository->create(['business_id' => $this->business->id, 'type' => 'product', 'name' => 'Coffee']);
    $second = $repository->create(['business_id' => $this->business->id, 'type' => 'product', 'name' => 'Tea']);
    expect($repository->forBusiness($this->business->id, 1, 1)->first()->id)->toBe($first->id)
        ->and($repository->forBusiness($this->business->id, 2, 1)->first()->id)->toBe($second->id)
        ->and($repository->forBusiness($this->business->id, 1, 1, true)->first()->id)->toBe($second->id);
    $repository->update($first->id, ['name' => 'Espresso']);
    expect($repository->forBusiness($this->business->id, 1, 1)->first()->name)->toBe('Espresso');
});

test('summary state refreshes on publication while locking and source reads remain live', function () {
    $repository = app(ReviewSummaryRepositoryInterface::class);
    $state = $repository->create([
        'business_id' => $this->business->id, 'status' => ReviewSummaryStatus::PENDING,
        'source_fingerprint' => str_repeat('a', 64), 'review_count' => 3, 'sampled_review_count' => 3,
    ]);
    expect($repository->forBusiness($this->business->id)->status)->toBe(ReviewSummaryStatus::PENDING);
    DB::transaction(function () use ($repository, $state) {
        $repository->lockBusiness($this->business->id);
        $repository->update($state->id, ['status' => ReviewSummaryStatus::READY, 'text' => 'Friendly service.']);
        expect($repository->forBusiness($this->business->id)->text)->toBe('Friendly service.');
    });
    expect($repository->forBusiness($this->business->id)->status)->toBe(ReviewSummaryStatus::READY);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $repository->snapshot($this->business->id);
    $repository->snapshot($this->business->id);
    expect(DB::getQueryLog())->toHaveCount(4);
});

test('cached collection ordering updates after moving and a revoked token remains inaccessible', function () {
    $saves = app(SavedBusinessRepositoryInterface::class);
    $collections = app(PlaceCollectionRepositoryInterface::class);
    $other = $this->business->replicate();
    $other->slug = 'second-cafe';
    $other->save();
    $collection = $collections->create([
        'user_id' => $this->consumer->id, 'name' => 'Stops', 'is_public' => true, 'share_token' => 'public-token',
    ]);
    foreach ([$this->business, $other] as $business) {
        $saves->saveForUser($this->consumer->id, $business->id);
        $saved = $saves->query()->where('business_id', $business->id)->first();
        $collections->addPlace($collection->id, $saved->id);
    }
    $saved = $saves->query()->where('business_id', $this->business->id)->first();
    $before = $collections->places($collection)->getCollection()->modelKeys();
    DB::transaction(fn () => $collections->movePlace($collection, $saved->id, 'down'));
    expect($collections->places($collection)->getCollection()->modelKeys())->toBe(array_reverse($before));
    expect($collections->findPublicByTokenOrFail('public-token')->id)->toBe($collection->id);
    $collections->update($collection->id, ['is_public' => false]);
    expect(fn () => $collections->findPublicByTokenOrFail('public-token'))
        ->toThrow(ModelNotFoundException::class);
});
