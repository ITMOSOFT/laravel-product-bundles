<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('itmosoft-product-bundles.table_prefix', 'itmosoft_');
        $bundlesTable = $prefix.'product_bundles';
        $itemsTable = $prefix.'product_bundle_items';

        if (! Schema::hasTable($bundlesTable)) {
            Schema::create($bundlesTable, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 180)->unique();
                $table->text('description')->nullable();
                $table->string('pricing_type', 20)->default('fixed')->index();
                $table->decimal('fixed_price', 16, 4)->nullable();
                $table->char('currency', 3)->default('USD');
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($itemsTable)) {
            Schema::create($itemsTable, function (Blueprint $table) use ($bundlesTable): void {
                $table->id();
                $table->unsignedBigInteger('bundle_id');
                $table->morphs('component');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 16, 4)->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('bundle_id')
                    ->references('id')
                    ->on($bundlesTable)
                    ->cascadeOnDelete();

                $table->unique(
                    ['bundle_id', 'component_type', 'component_id'],
                    'product_bundle_component_unique'
                );
                $table->index(['bundle_id', 'position'], 'product_bundle_position_index');
            });
        }
    }

    public function down(): void
    {
        $prefix = (string) config('itmosoft-product-bundles.table_prefix', 'itmosoft_');
        Schema::dropIfExists($prefix.'product_bundle_items');
        Schema::dropIfExists($prefix.'product_bundles');
    }
};
