<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Who made each sale and registered each payment, for the cash audit. Past sales take the
     * user from their stock movements, which already recorded it; past payments stay unknown.
     */
    public function up(): void
    {
        foreach (['sales', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        DB::statement('UPDATE sales SET user_id = (
            SELECT stock_movements.user_id FROM stock_movements
            WHERE stock_movements.sale_id = sales.id AND stock_movements.user_id IS NOT NULL
            LIMIT 1
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['sales', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
