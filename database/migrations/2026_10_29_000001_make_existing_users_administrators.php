<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Roles started being enforced (Administrador: everything, Vendedor: POS only), so the
     * accounts created before keep full access. New accounts start as Vendedor.
     */
    public function up(): void
    {
        DB::table('users')->update(['role' => 'Administrador']);
    }

    /**
     * Reverse the migrations.
     *
     * Left empty: the previous role ("Médico Veterinario") granted nothing, there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
