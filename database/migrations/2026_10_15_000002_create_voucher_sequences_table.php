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
     * One counter per voucher prefix (C = contado, R = remito). Each counter starts at the highest
     * number already issued with that prefix, so existing vouchers are never repeated.
     */
    public function up(): void
    {
        Schema::create('voucher_sequences', function (Blueprint $table) {
            $table->string('prefix', 5)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        foreach (['C', 'R'] as $prefix) {
            $last = DB::table('sales')
                ->where('voucher_number', 'like', $prefix.'-%')
                ->pluck('voucher_number')
                ->map(fn (string $voucher) => (int) substr($voucher, strlen($prefix) + 1))
                ->max() ?? 0;

            DB::table('voucher_sequences')->insert([
                'prefix' => $prefix,
                'last_number' => $last,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_sequences');
    }
};
