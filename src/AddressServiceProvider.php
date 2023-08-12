<?php

namespace Yajra\Address;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Yajra\Address\Controllers\BarangaysController;
use Yajra\Address\Controllers\CitiesController;
use Yajra\Address\Controllers\ProvincesController;
use Yajra\Address\Controllers\RegionsController;
use Yajra\Address\Repositories\Barangays\BarangaysRepository;
use Yajra\Address\Repositories\Barangays\BarangaysRepositoryEloquent;
use Yajra\Address\Repositories\Barangays\CachingBarangaysRepository;
use Yajra\Address\Repositories\Cities\CachingCitiesRepository;
use Yajra\Address\Repositories\Cities\CitiesRepository;
use Yajra\Address\Repositories\Cities\CitiesRepositoryEloquent;
use Yajra\Address\Repositories\Provinces\CachingProvincesRepository;
use Yajra\Address\Repositories\Provinces\ProvincesRepository;
use Yajra\Address\Repositories\Provinces\ProvincesRepositoryEloquent;
use Yajra\Address\Repositories\Regions\CachingRegionsRepository;
use Yajra\Address\Repositories\Regions\RegionsRepository;
use Yajra\Address\Repositories\Regions\RegionsRepositoryEloquent;

class AddressServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->mergeConfigFrom($config = __DIR__ . '/../config/address.php', 'address');
        if ($this->app->runningInConsole()) {
            $this->publishes([$config => config_path('address.php')], 'address');
        }

        $this->setupViews();
        // $this->setupRoutes();
        $this->setupMacro();
    }

    protected function setupViews()
    {
        view()->composer('address::form', function() {
            /** @var RegionsRepository $repo */
            $repo = app(RegionsRepository::class);
            view()->share('regions', $repo->all()->pluck('name', 'region_id'));
        });

        $this->loadViewsFrom(__DIR__ . '/Views', 'address');

        $this->publishes([
            __DIR__ . '/Views' => resource_path('views/vendor/address'),
        ], 'address');
    }

    protected function setupRoutes()
    {
        Route::group([
            'prefix'     => config('address.prefix'),
            'middleware' => config('address.middleware'),
            'as'         => 'address.',
        ], function () {
            Route::get('regions', RegionsController::class . '@all')->name('regions.all');
            Route::get('provinces', ProvincesController::class . '@all')->name('provinces.all');
            Route::get('provinces/{regionId}', ProvincesController::class . '@getByRegion')->name('provinces.region');
            Route::get('cities/{provinceId}', CitiesController::class . '@getByProvince')->name('cities.province');
            Route::get('cities/{regionId}/{provinceId}', CitiesController::class . '@getByRegionAndProvince')
                 ->name('cities.region.province');
            Route::get('barangays/{cityId}', BarangaysController::class . '@getByCity')->name('barangay.city');
        });
    }

    protected function setupMacro()
    {
        Blueprint::macro('address', function () {
            $this->string('street')->nullable();
            $this->string('barangay_id', 9)->nullable()->index();
            $this->string('city_id', 6)->nullable()->index();
            $this->string('province_id', 4)->nullable()->index();
            $this->string('region_id', 2)->nullable()->index();
        });

        Blueprint::macro('dropAddress', function () {
            $this->dropColumn(['region_id', 'province_id', 'city_id', 'barangay_id', 'street']);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RegionsRepository::class, function () {
            return new CachingRegionsRepository(
                $this->app->make(RegionsRepositoryEloquent::class),
                $this->app['cache.store']
            );
        });
        $this->app->singleton(ProvincesRepository::class, function () {
            return new CachingProvincesRepository(
                $this->app->make(ProvincesRepositoryEloquent::class),
                $this->app['cache.store']
            );
        });
        $this->app->singleton(CitiesRepository::class, function () {
            return new CachingCitiesRepository(
                $this->app->make(CitiesRepositoryEloquent::class),
                $this->app['cache.store']
            );
        });
        $this->app->singleton(BarangaysRepository::class, function () {
            return new CachingBarangaysRepository(
                $this->app->make(BarangaysRepositoryEloquent::class),
                $this->app['cache.store']
            );
        });
    }
}
