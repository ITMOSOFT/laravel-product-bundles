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

        if ($pricingType === 'fixed' && ! isset($attributes['fixed_price'])) {
            throw new InvalidArgumentException('A fixed price is required for fixed-price bundles.');
        }

        return ProductBundle::query()->create(Arr::only($attributes, [
            'name', 'slug', 'description', 'pricing_type', 'fixed_price',
            'currency', 'is_active', 'metadata',
        ]));
    }

    public function findBundle(ProductBundle|string|int $bundle): ProductBundle
    {
        if ($bundle instanceof ProductBundle) {
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
        if ($quantity < 1) {
            throw new InvalidArgumentException('Component quantity must be at least 1.');
        }

        if (! is_numeric($unitPrice) || (float) $unitPrice < 0) {
            throw new InvalidArgumentException('Component unit price must be a non-negative number.');
        }

        $bundleModel = $this->findBundle($bundle);

        return $bundleModel->items()->create([
            'component_type' => $component->getMorphClass(),
            'component_id' => $component->getKey(),
            'quantity' => $quantity,
            'unit_price' => number_format((float) $unitPrice, 4, '.', ''),
            'position' => $position,
        ]);
    }

    public function removeComponent(ProductBundle|string|int $bundle, Model $component): int
    {
        $bundleModel = $this->findBundle($bundle);

        return $bundleModel->items()
            ->where('component_type', $component->getMorphClass())
            ->where('component_id', $component->getKey())
            ->delete();
    }

    /**
     * Replace all bundle components. Each entry must contain component (Model),
     * quantity (int), unit_price (numeric), and optionally position (int).
     *
     * @param array<int, array{component: Model, quantity?: int, unit_price?: string|int|float, position?: int}> $components
     */
    public function syncComponents(ProductBundle|string|int $bundle, array $components): void
    {
        $bundleModel = $this->findBundle($bundle);

        DB::transaction(function () use ($bundleModel, $components): void {
            $bundleModel->items()->delete();

            foreach ($components as $component) {
                if (! ($component['component'] ?? null) instanceof Model) {
                    throw new InvalidArgumentException('Every bundle component must be an Eloquent model.');
                }

                $this->addComponent(
                    $bundleModel,
                    $component['component'],
                    (int) ($component['quantity'] ?? 1),
                    $component['unit_price'] ?? '0.0000',
                    (int) ($component['position'] ?? 0)
                );
            }
        });
    }

    /**
     * Returns a decimal string rounded to four fractional digits.
     *
     * Dynamic bundle pricing uses the component unit-price snapshots stored
     * when components are attached. Refresh these snapshots when catalog prices change.
     */
    public function calculatePrice(ProductBundle|string|int $bundle): string
    {
        $bundleModel = $this->findBundle($bundle);

        if ($bundleModel->pricing_type === 'fixed') {
            return number_format((float) $bundleModel->fixed_price, 4, '.', '');
        }

        $total = 0.0;

        foreach ($bundleModel->items as $item) {
            $total += (float) $item->unit_price * (int) $item->quantity;
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
}
