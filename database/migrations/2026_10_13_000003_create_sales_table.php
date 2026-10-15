<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number', 30)->unique();
            $table->enum('type', ['contado', 'remito'])->default('contado');
            $table->foreignId('producer_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('status', 20)->default('pendiente');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['producer_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
