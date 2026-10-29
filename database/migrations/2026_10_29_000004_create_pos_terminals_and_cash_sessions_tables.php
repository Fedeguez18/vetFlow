<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The mostrador works on PCs an administrator enabled as terminals: it asks for the user's
     * POS code, opens a caja in their name and only locks again when that caja is closed.
     * Sellers only have a code, so email and password become optional.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('pos_code', 64)->nullable()->unique()->after('role');
        });

        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('declared_cash', 12, 2)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pos_terminal_id', 'closed_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('pos_terminals');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pos_code']);
            $table->dropColumn('pos_code');
        });
    }
};
