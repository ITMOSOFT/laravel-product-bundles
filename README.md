# Laravel Product Bundles

Manage fixed-price and component-priced product bundles in Laravel applications.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-product-bundles
php artisan vendor:publish --tag=itmosoft-product-bundles-config
php artisan migrate
```

## Create a bundle

```php
use ItmoSoft\ProductBundles\ProductBundleManager;

$bundles = app(ProductBundleManager::class);

$fixed = $bundles->createBundle([
    'name' => 'Starter Kit',
    'description' => 'A curated starter package',
    'pricing_type' => 'fixed',
    'fixed_price' => '49.9500',
    'currency' => 'USD',
]);

$dynamic = $bundles->createBundle([
    'name' => 'Build Your Own Kit',
    'pricing_type' => 'dynamic',
    'currency' => 'USD',
]);
```

## Add components

Components are any Eloquent model, including catalog products or product variants. The package stores a polymorphic reference and a unit-price snapshot.

```php
$bundles->addComponent(
    $dynamic,
    $product,
    quantity: 2,
    unitPrice: '12.5000',
    position: 1,
);

$price = $bundles->calculatePrice($dynamic);
```

Dynamic pricing is calculated from the stored component unit-price snapshots multiplied by their quantities. Refresh snapshots through your application when catalog prices change. The package does not assume a particular product schema or automatically fetch product prices.

## Synchronize components

```php
$bundles->syncComponents($dynamic, [
    [
        'component' => $keyboard,
        'quantity' => 1,
        'unit_price' => '25.0000',
        'position' => 1,
    ],
    [
        'component' => $mouse,
        'quantity' => 2,
        'unit_price' => '10.0000',
        'position' => 2,
    ],
]);
```

## Other operations

```php
$bundles->removeComponent($dynamic, $mouse);
$bundles->setActive($dynamic, false);
$bundles->findBundle('starter-kit');
$bundles->activeBundles();
$bundles->deleteBundle($dynamic);
```

## Database tables

Default tables:

- `itmosoft_product_bundles`
- `itmosoft_product_bundle_items`

Change the `table_prefix` in `config/itmosoft-product-bundles.php` before running migrations. Changing it after installation requires a corresponding database migration.

## Integration boundaries

This package owns bundle definitions, component references, quantities, and bundle price calculation. It does not modify inventory, expand bundles into cart/order lines, reserve stock, or process payments. Integrate those workflows with `laravel-inventory`, `laravel-cart`, and `laravel-orders` in the host application.

## Testing

```bash
composer install
composer test
```

## License

MIT.
