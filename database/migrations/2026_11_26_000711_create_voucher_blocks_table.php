<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each POS terminal numbers its tickets and remitos from blocks of correlative numbers taken
     * from voucher_sequences beforehand, so it can keep printing real numbers without internet.
     */
    public function up(): void
    {
        Schema::create('voucher_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_terminal_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 5);
            $table->unsignedBigInteger('first_number');
            $table->unsignedBigInteger('last_number');
            $table->unsignedBigInteger('next_number');
            $table->timestamps();

            $table->index(['pos_terminal_id', 'prefix']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_blocks');
    }
};
