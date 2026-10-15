<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs on deploy when SEED_DEMO=true (see Dockerfile). Only seeds an empty catalog, so redeploys never duplicate data.
 */
class SeedDemo extends Command
{
    protected $signature = 'app:seed-demo';

    protected $description = 'Load the demo catalog, customers and sales into an empty database';

    public function handle(): int
    {
        if (Product::exists()) {
            $this->info('La base ya tiene productos: no se cargan datos de demo.');

            return self::SUCCESS;
        }

        if (! User::exists()) {
            $this->error('No hay usuarios: creá el administrador antes de cargar los datos de demo.');

            return self::FAILURE;
        }

        DB::transaction(fn () => $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]));

        $this->info('Datos de demo cargados: '.Product::count().' productos.');

        return self::SUCCESS;
    }
}
