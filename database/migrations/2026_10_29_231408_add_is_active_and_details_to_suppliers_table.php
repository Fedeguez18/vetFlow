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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('business_name');
            $table->string('iva_condition', 40)->nullable()->after('cuit');
            $table->string('address')->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('payment_terms')->nullable()->after('city');
            $table->text('notes')->nullable()->after('payment_terms');
            $table->boolean('is_active')->default(true)->index()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['contact_name', 'iva_condition', 'address', 'city', 'payment_terms', 'notes', 'is_active']);
        });
    }
};
