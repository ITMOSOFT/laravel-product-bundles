<?php

namespace ItmoSoft\ProductBundles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductBundle extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'pricing_type' => 'fixed',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'fixed_price' => 'decimal:4',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return (string) Config::get('itmosoft-product-bundles.table_prefix', 'itmosoft_').'product_bundles';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id')->orderBy('position')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::saving(function (ProductBundle $bundle): void {
            if (blank($bundle->name)) {
                throw new InvalidArgumentException('A product bundle name is required.');
            }

            if (blank($bundle->slug) && filled($bundle->name)) {
                $bundle->slug = Str::slug($bundle->name);
            }

            if (blank($bundle->slug)) {
                throw new InvalidArgumentException('A product bundle slug is required.');
            }

            if (! in_array($bundle->pricing_type, ['fixed', 'dynamic'], true)) {
                throw new InvalidArgumentException('Pricing type must be fixed or dynamic.');
            }

            $currency = strtoupper(trim((string) ($bundle->currency ?: 'USD')));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('Bundle currency must be a three-letter code.');
            }
            $bundle->currency = $currency;

            if ($bundle->pricing_type === 'fixed') {
                $value = $bundle->fixed_price;
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
                    throw new InvalidArgumentException('Fixed bundle price must be a finite, non-negative number.');
                }
            } elseif ($bundle->fixed_price !== null) {
                throw new InvalidArgumentException('Dynamic bundles must not define a fixed price.');
            }
        });
    }
}
