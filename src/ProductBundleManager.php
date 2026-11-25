<?php

namespace ItmoSoft\ProductBundles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ItmoSoft\ProductBundles\Models\ProductBundle;
use ItmoSoft\ProductBundles\Models\ProductBundleItem;

class ProductBundleManager
{
    public function createBundle(array $attributes): ProductBundle
    {
        if (blank($attributes['name'] ?? null)) {
            throw new InvalidArgumentException('A product bundle name is required.');
        }

        $pricingType = $attributes['pricing_type'] ?? 'fixed';

        if (! in_array($pricingType, ['fixed', 'dynamic'], true)) {
            throw new InvalidArgumentException('Pricing type must be fixed or dynamic.');
        }

        if ($pricingType === 'fixed') {
            $this->nonNegativeFiniteNumber($attributes['fixed_price'] ?? null, 'fixed bundle price');
        } elseif (($attributes['fixed_price'] ?? null) !== null) {
            throw new InvalidArgumentException('Dynamic bundles must not define a fixed price.');
        }

        $currency = strtoupper(trim((string) ($attributes['currency'] ?? 'USD')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Bundle currency must be a three-letter code.');
        }

        $attributes['currency'] = $currency;

        return ProductBundle::query()->create(Arr::only($attributes, [
            'name', 'slug', 'description', 'pricing_type', 'fixed_price',
            'currency', 'is_active', 'metadata',
        ]));
    }

    public function findBundle(ProductBundle|string|int $bundle): ProductBundle
    {
        if ($bundle instanceof ProductBundle) {
            if (! $bundle->exists || $bundle->getKey() === null) {
                throw new InvalidArgumentException('The product bundle must be persisted.');
            }

            return $bundle;
        }

        $query = ProductBundle::query();

        $found = is_int($bundle) || ctype_digit((string) $bundle)
            ? $query->find($bundle)
            : $query->where('slug', $bundle)->first();

        if (! $found) {
            throw new InvalidArgumentException('The requested product bundle does not exist.');
        }

        return $found;
    }

    public function addComponent(
        ProductBundle|string|int $bundle,
        Model $component,
        int $quantity = 1,
        string|int|float $unitPrice = '0.0000',
        int $position = 0
    ): ProductBundleItem {
        if (! $component->exists || $component->getKey() === null) {
            throw new InvalidArgumentException('Bundle components must be persisted Eloquent models.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Component quantity must be at least 1.');
        }

        if ($position < 0) {
            throw new InvalidArgumentException('Component position cannot be negative.');
        }

        $price = $this->nonNegativeFiniteNumber($unitPrice, 'component unit price');
        $bundleModel = $this->findBundle($bundle);

        return $bundleModel->items()->create([
            'component_type' => $component->getMorphClass(),
            'component_id' => $component->getKey(),
            'quantity' => $quantity,
            'unit_price' => number_format($price, 4, '.', ''),
            'position' => $position,
        ]);
    }

    public function removeComponent(ProductBundle|string|int $bundle, Model $component): int
    {
        if (! $component->exists || $component->getKey() === null) {
            throw new InvalidArgumentException('Bundle components must be persisted Eloquent models.');
        }

        $bundleModel = $this->findBundle($bundle);

        return $bundleModel->items()
            ->where('component_type', $component->getMorphClass())
            ->where('component_id', $component->getKey())
            ->delete();
    }

    /**
     * Replace all bundle components atomically.
     *
     * @param array<int, array{component: Model, quantity?: int, unit_price?: string|int|float, position?: int}> $components
     */
    public function syncComponents(ProductBundle|string|int $bundle, array $components): void
    {
        $bundleModel = $this->findBundle($bundle);

        // Validate the complete replacement before deleting existing rows.
        foreach ($components as $entry) {
            if (! is_array($entry) || ! (($entry['component'] ?? null) instanceof Model)) {
                throw new InvalidArgumentException('Every bundle component must be an Eloquent model.');
            }

            $component = $entry['component'];
            if (! $component->exists || $component->getKey() === null) {
                throw new InvalidArgumentException('Bundle components must be persisted Eloquent models.');
            }

            $quantity = $entry['quantity'] ?? 1;
            if (! is_int($quantity) || $quantity < 1) {
                throw new InvalidArgumentException('Component quantity must be a positive integer.');
            }

            $position = $entry['position'] ?? 0;
            if (! is_int($position) || $position < 0) {
                throw new InvalidArgumentException('Component position must be a non-negative integer.');
            }

            $this->nonNegativeFiniteNumber($entry['unit_price'] ?? '0.0000', 'component unit price');
        }

        DB::transaction(function () use ($bundleModel, $components): void {
            $lockedBundle = ProductBundle::query()->lockForUpdate()->findOrFail($bundleModel->getKey());
            $lockedBundle->items()->delete();

            foreach ($components as $entry) {
                $this->addComponent(
                    $lockedBundle,
                    $entry['component'],
                    $entry['quantity'] ?? 1,
                    $entry['unit_price'] ?? '0.0000',
                    $entry['position'] ?? 0
                );
            }
        });
    }

    /**
     * Returns a decimal string rounded to four fractional digits.
     * Dynamic bundle pricing uses component unit-price snapshots.
     */
    public function calculatePrice(ProductBundle|string|int $bundle): string
    {
        $bundleModel = $this->findBundle($bundle);

        if ($bundleModel->pricing_type === 'fixed') {
            return number_format(
                $this->nonNegativeFiniteNumber($bundleModel->fixed_price, 'fixed bundle price'),
                4,
                '.',
                ''
            );
        }

        $total = 0.0;

        foreach ($bundleModel->items as $item) {
            $unitPrice = $this->nonNegativeFiniteNumber($item->unit_price, 'component unit price');
            $quantity = (int) $item->quantity;

            if ($quantity < 1) {
                throw new InvalidArgumentException('Stored component quantity must be at least 1.');
            }

            $lineTotal = $unitPrice * $quantity;
            if (! is_finite($lineTotal)) {
                throw new InvalidArgumentException('Calculated component total is outside the supported numeric range.');
            }

            $total += $lineTotal;
            if (! is_finite($total)) {
                throw new InvalidArgumentException('Calculated bundle total is outside the supported numeric range.');
            }
        }

        return number_format($total, 4, '.', '');
    }

    public function setActive(ProductBundle|string|int $bundle, bool $active): ProductBundle
    {
        $bundleModel = $this->findBundle($bundle);
        $bundleModel->forceFill(['is_active' => $active])->save();

        return $bundleModel->refresh();
    }

    public function deleteBundle(ProductBundle|string|int $bundle): void
    {
        $this->findBundle($bundle)->delete();
    }

    public function activeBundles()
    {
        return ProductBundle::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function nonNegativeFiniteNumber(mixed $value, string $field): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("The {$field} must be numeric.");
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0) {
            throw new InvalidArgumentException("The {$field} must be finite and non-negative.");
        }

        return $number;
    }
}
