<?php

namespace App\Providers;

use App\Models\Business;
use App\Repositories\Caching\BusinessCacheRepository;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use App\Repositories\Contracts\BusinessRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\PlaceCollectionRepositoryInterface;
use App\Repositories\Contracts\SavedBusinessRepositoryInterface;
use App\Repositories\Eloquents\BusinessOfferingRepository;
use App\Repositories\Eloquents\BusinessRepository as EloquentBusinessRepository;
use App\Repositories\Eloquents\CategoryRepository;
use App\Repositories\Eloquents\PlaceCollectionRepository;
use App\Repositories\Eloquents\SavedBusinessRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BusinessRepositoryInterface::class, function ($app) {
            $eloquentRepo = new EloquentBusinessRepository(
                $app->make(Business::class)
            );

            return new BusinessCacheRepository($eloquentRepo);
        });

        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(PlaceCollectionRepositoryInterface::class, PlaceCollectionRepository::class);
        $this->app->bind(SavedBusinessRepositoryInterface::class, SavedBusinessRepository::class);
        $this->app->bind(BusinessOfferingRepositoryInterface::class, BusinessOfferingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
