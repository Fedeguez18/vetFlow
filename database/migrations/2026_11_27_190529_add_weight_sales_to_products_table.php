<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products sold by weight (loose dog food): price per kg, stock and sold quantities in grams.
     * A closed bag can point to its loose product so opening it moves its kilos there.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('sold_by_weight')->default(false)->after('tracks_expiry');
            $table->foreignId('bulk_product_id')->nullable()->after('sold_by_weight')->constrained('products')->nullOnDelete();
            $table->unsignedInteger('bag_weight_grams')->nullable()->after('bulk_product_id');
        });

        // Snapshot: the item keeps meaning grams even if the product is changed later.
        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('sold_by_weight')->default(false)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('sold_by_weight');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bulk_product_id');
            $table->dropColumn(['sold_by_weight', 'bag_weight_grams']);
        });
    }
};
