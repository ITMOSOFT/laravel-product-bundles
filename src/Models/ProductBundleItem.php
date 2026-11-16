<?php

namespace ItmoSoft\ProductBundles\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;

class ProductBundleItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:4',
            'position' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return (string) Config::get('itmosoft-product-bundles.table_prefix', 'itmosoft_').'product_bundle_items';
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    public function component(): MorphTo
    {
        return $this->morphTo();
    }
}
