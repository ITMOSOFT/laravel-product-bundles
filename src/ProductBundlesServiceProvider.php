<?php

namespace ItmoSoft\ProductBundles;

use Illuminate\Support\ServiceProvider;

class ProductBundlesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/itmosoft-product-bundles.php',
            'itmosoft-product-bundles'
        );

        $this->app->singleton(ProductBundleManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/itmosoft-product-bundles.php' => config_path('itmosoft-product-bundles.php'),
        ], 'itmosoft-product-bundles-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
