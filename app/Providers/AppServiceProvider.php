<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\Category;
use App\Models\PlaceCollection;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewSummary;
use App\Models\SavedBusiness;
use App\Models\User;
use App\Repositories\Caching\BusinessCacheRepository;
use App\Repositories\Caching\BusinessOfferingCacheRepository;
use App\Repositories\Caching\CategoryCacheRepository;
use App\Repositories\Caching\NotificationCacheRepository;
use App\Repositories\Caching\PlaceCollectionCacheRepository;
use App\Repositories\Caching\ReviewCacheRepository;
use App\Repositories\Caching\ReviewSummaryCacheRepository;
use App\Repositories\Caching\SavedBusinessCacheRepository;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\ReviewSummaryRepositoryInterface;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use App\Repositories\Eloquents\BusinessOfferingRepository;
use App\Repositories\Eloquents\BusinessRepository;
use App\Repositories\Eloquents\CategoryRepository;
use App\Repositories\Eloquents\NotificationRepository;
use App\Repositories\Eloquents\PlaceCollectionRepository;
use App\Repositories\Eloquents\ReviewRepository;
use App\Repositories\Eloquents\ReviewSummaryRepository;
use App\Repositories\Eloquents\SavedBusinessRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Each contract resolves to a cache decorator around its Eloquent repository.
     */
    private array $repositories = [
        BusinessRepositoryInterface::class => [
            BusinessRepository::class,
            BusinessCacheRepository::class,
        ],
        CategoryRepositoryInterface::class => [
            CategoryRepository::class,
            CategoryCacheRepository::class,
        ],
        BusinessOfferingRepositoryInterface::class => [
            BusinessOfferingRepository::class,
            BusinessOfferingCacheRepository::class,
        ],
        SavedBusinessRepositoryInterface::class => [
            SavedBusinessRepository::class,
            SavedBusinessCacheRepository::class,
        ],
        PlaceCollectionRepositoryInterface::class => [
            PlaceCollectionRepository::class,
            PlaceCollectionCacheRepository::class,
        ],
        ReviewRepositoryInterface::class => [
            ReviewRepository::class,
            ReviewCacheRepository::class,
        ],
        ReviewSummaryRepositoryInterface::class => [
            ReviewSummaryRepository::class,
            ReviewSummaryCacheRepository::class,
        ],
        NotificationRepositoryInterface::class => [
            NotificationRepository::class,
            NotificationCacheRepository::class,
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ($this->repositories as $contract => [$eloquent, $caching]) {
            $factory = function (Application $app) use ($eloquent, $caching) {
                $eloquentRepo = $app->make($eloquent);

                return new $caching($eloquentRepo);
            };

            $this->app->bind($contract, $factory);
            $this->app->bind($caching, $factory);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerCacheInvalidation();
    }

    /**
     * Cover direct model writes and cascades as well as repository mutations.
     */
    private function registerCacheInvalidation(): void
    {
        $models = [
            Business::class => BusinessCacheRepository::class,
            Category::class => CategoryCacheRepository::class,
            BusinessOffering::class => BusinessOfferingCacheRepository::class,
            SavedBusiness::class => SavedBusinessCacheRepository::class,
            PlaceCollection::class => PlaceCollectionCacheRepository::class,
            Review::class => ReviewCacheRepository::class,
            ReviewSummary::class => ReviewSummaryCacheRepository::class,
            ReviewReply::class => ReviewCacheRepository::class,
        ];

        foreach ($models as $model => $caching) {
            $invalidate = fn () => $this->app->make($caching)->invalidate();

            $model::saved($invalidate);
            $model::deleted($invalidate);
        }

        // Account names and cascading deletes affect several cached relationships.
        $invalidateUser = function () {
            foreach ($this->repositories as [$eloquent, $caching]) {
                $this->app->make($caching)->invalidate();
            }
        };

        User::saved($invalidateUser);
        User::deleted($invalidateUser);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
