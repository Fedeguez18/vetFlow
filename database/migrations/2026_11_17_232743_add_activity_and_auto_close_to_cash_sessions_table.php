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
     * A caja closes by itself after hours without use on the mostrador (without a cash count),
     * and another user can close it counting the cash: keep who closed it and how.
     */
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('opening_amount');
            $table->boolean('closed_automatically')->default(false)->after('declared_cash');
            $table->foreignId('closed_by')->nullable()->after('closed_automatically')->constrained('users')->nullOnDelete();
        });

        DB::table('cash_sessions')->update(['last_activity_at' => DB::raw('created_at')]);

        // Open cajas count their last sale as activity, so they don't close right after the deploy.
        DB::table('sales')
            ->join('cash_sessions', 'cash_sessions.id', '=', 'sales.cash_session_id')
            ->whereNull('cash_sessions.closed_at')
            ->groupBy('sales.cash_session_id')
            ->selectRaw('sales.cash_session_id, max(sales.created_at) as last_sale_at')
            ->get()
            ->each(fn ($row) => DB::table('cash_sessions')
                ->where('id', $row->cash_session_id)
                ->where('last_activity_at', '<', $row->last_sale_at)
                ->update(['last_activity_at' => $row->last_sale_at]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['last_activity_at', 'closed_automatically']);
        });
    }
};
