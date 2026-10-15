<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The column was later added to the original users migration, so databases
     * migrated before that change (e.g. production on Neon) are missing it.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 60)->default('Médico Veterinario')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Left empty: on fresh databases the column belongs to the create_users_table migration.
     */
    public function down(): void
    {
        //
    }
};
