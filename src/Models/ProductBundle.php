<?php

namespace ItmoSoft\ProductBundles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

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
        static::creating(function (ProductBundle $bundle): void {
            if (blank($bundle->slug) && filled($bundle->name)) {
                $bundle->slug = Str::slug($bundle->name);
            }
        });
    }
}
