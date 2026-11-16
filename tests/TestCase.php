<?php

namespace ItmoSoft\ProductBundles\Tests;

use Illuminate\Support\Facades\Schema;
use ItmoSoft\ProductBundles\ProductBundlesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ProductBundlesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('itmosoft-product-bundles.table_prefix', 'itmosoft_');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_components', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->artisan('migrate', ['--database' => 'testing'])->run();

        $this->assertTrue(Schema::hasTable('itmosoft_product_bundles'));
        $this->assertTrue(Schema::hasTable('itmosoft_product_bundle_items'));
    }
}
