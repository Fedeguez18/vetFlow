<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sales made by the POS without internet are uploaded later: the id the browser gave them makes
     * the upload idempotent, synced_at tells them apart, and needs_review / sync_notes flag what
     * administración has to check (stock that wasn't there, a renumbered voucher...).
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('offline_uuid')->nullable()->unique()->after('cash_session_id');
            $table->timestamp('synced_at')->nullable()->after('offline_uuid');
            $table->boolean('needs_review')->default(false)->after('synced_at');
            $table->text('sync_notes')->nullable()->after('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['offline_uuid']);
            $table->dropColumn(['offline_uuid', 'synced_at', 'needs_review', 'sync_notes']);
        });
    }
};
