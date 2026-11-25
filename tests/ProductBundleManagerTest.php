<?php

namespace ItmoSoft\ProductBundles\Tests;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ItmoSoft\ProductBundles\ProductBundleManager;
use ItmoSoft\ProductBundles\Models\ProductBundle;
use ItmoSoft\ProductBundles\Models\ProductBundleItem;

class ProductBundleManagerTest extends TestCase
{
    public function test_it_creates_a_fixed_price_bundle_and_generates_slug(): void
    {
        $bundle = app(ProductBundleManager::class)->createBundle([
            'name' => 'Starter Kit',
            'fixed_price' => '49.9500',
        ]);

        $this->assertSame('starter-kit', $bundle->slug);
        $this->assertSame('fixed', $bundle->pricing_type);
        $this->assertSame('49.9500', app(ProductBundleManager::class)->calculatePrice($bundle));
    }

    public function test_it_creates_a_dynamic_bundle(): void
    {
        $bundle = app(ProductBundleManager::class)->createBundle([
            'name' => 'Build Your Own',
            'pricing_type' => 'dynamic',
        ]);

        $this->assertSame('dynamic', $bundle->pricing_type);
    }

    public function test_it_rejects_missing_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->createBundle([]);
    }

    public function test_it_requires_fixed_price_for_fixed_bundle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->createBundle(['name' => 'No Price']);
    }

    public function test_it_rejects_unknown_pricing_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->createBundle([
            'name' => 'Invalid',
            'pricing_type' => 'custom',
        ]);
    }

    public function test_it_adds_components_and_calculates_dynamic_price(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle([
            'name' => 'Office Set',
            'pricing_type' => 'dynamic',
        ]);
        $keyboard = TestComponent::query()->create(['name' => 'Keyboard']);
        $mouse = TestComponent::query()->create(['name' => 'Mouse']);

        $manager->addComponent($bundle, $keyboard, 1, '25.5000');
        $manager->addComponent($bundle, $mouse, 2, '10.2500');

        $this->assertSame('46.0000', $manager->calculatePrice($bundle));
        $this->assertCount(2, $bundle->fresh()->items);
    }

    public function test_it_rejects_zero_component_quantity(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);

        $this->expectException(InvalidArgumentException::class);
        $manager->addComponent($bundle, $component, 0, '1.00');
    }

    public function test_it_rejects_negative_component_price(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);

        $this->expectException(InvalidArgumentException::class);
        $manager->addComponent($bundle, $component, 1, '-1.00');
    }

    public function test_it_removes_a_component(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);
        $manager->addComponent($bundle, $component);

        $this->assertSame(1, $manager->removeComponent($bundle, $component));
        $this->assertDatabaseCount('itmosoft_product_bundle_items', 0);
    }

    public function test_it_syncs_components_transactionally(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'pricing_type' => 'dynamic']);
        $first = TestComponent::query()->create(['name' => 'First']);
        $second = TestComponent::query()->create(['name' => 'Second']);

        $manager->syncComponents($bundle, [
            ['component' => $first, 'quantity' => 2, 'unit_price' => '5.00'],
            ['component' => $second, 'quantity' => 1, 'unit_price' => '7.00'],
        ]);

        $this->assertSame('17.0000', $manager->calculatePrice($bundle));
        $this->assertCount(2, $bundle->fresh()->items);
    }

    public function test_it_sets_bundle_active_state(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);

        $updated = $manager->setActive($bundle, false);

        $this->assertFalse($updated->is_active);
    }

    public function test_it_lists_only_active_bundles(): void
    {
        $manager = app(ProductBundleManager::class);
        $manager->createBundle(['name' => 'Active', 'fixed_price' => '10']);
        $inactive = $manager->createBundle([
            'name' => 'Inactive',
            'fixed_price' => '10',
            'is_active' => false,
        ]);

        $this->assertCount(1, $manager->activeBundles());
        $this->assertFalse($inactive->is_active);
    }

    public function test_it_deletes_bundle_and_cascades_items(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);
        $manager->addComponent($bundle, $component);

        $manager->deleteBundle($bundle);

        $this->assertDatabaseMissing('itmosoft_product_bundles', ['id' => $bundle->id]);
        $this->assertDatabaseCount('itmosoft_product_bundle_items', 0);
    }

    public function test_it_finds_bundle_by_slug(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Travel Pack', 'fixed_price' => '25']);

        $this->assertSame($bundle->id, $manager->findBundle('travel-pack')->id);
    }

    public function test_it_rejects_unsaved_component_models(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $unsaved = new TestComponent();

        $this->expectException(InvalidArgumentException::class);
        $manager->addComponent($bundle, $unsaved);
    }

    public function test_it_rejects_unsaved_bundle_instances(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->findBundle(new ProductBundle(['name' => 'Unsaved']));
    }

    public function test_it_rejects_non_finite_component_prices(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);

        $this->expectException(InvalidArgumentException::class);
        $manager->addComponent($bundle, $component, 1, INF);
    }

    public function test_it_rejects_invalid_fixed_bundle_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->createBundle([
            'name' => 'Invalid Price',
            'fixed_price' => INF,
        ]);
    }

    public function test_it_rejects_invalid_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProductBundleManager::class)->createBundle([
            'name' => 'Invalid Currency',
            'fixed_price' => '10',
            'currency' => 'USDD',
        ]);
    }

    public function test_it_rejects_negative_component_position(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'fixed_price' => '10']);
        $component = TestComponent::query()->create(['name' => 'Item']);

        $this->expectException(InvalidArgumentException::class);
        $manager->addComponent($bundle, $component, 1, '1.00', -1);
    }

    public function test_invalid_sync_does_not_delete_existing_components(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'pricing_type' => 'dynamic']);
        $existing = TestComponent::query()->create(['name' => 'Existing']);
        $unsaved = new TestComponent();

        $manager->addComponent($bundle, $existing, 1, '9.00');

        try {
            $manager->syncComponents($bundle, [
                ['component' => $unsaved, 'quantity' => 1, 'unit_price' => '2.00'],
            ]);
            $this->fail('Expected invalid component to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseHas('itmosoft_product_bundle_items', [
                'bundle_id' => $bundle->id,
                'component_id' => $existing->id,
            ]);
        }
    }

    public function test_dynamic_total_rejects_invalid_stored_component_quantity(): void
    {
        $manager = app(ProductBundleManager::class);
        $bundle = $manager->createBundle(['name' => 'Bundle', 'pricing_type' => 'dynamic']);

        // Simulate corrupted legacy data without bypassing database constraints.
        $invalidItem = new ProductBundleItem([
            'quantity' => 0,
            'unit_price' => '1.0000',
        ]);
        $bundle->setRelation('items', collect([$invalidItem]));

        $this->expectException(InvalidArgumentException::class);
        $manager->calculatePrice($bundle);
    }
}
