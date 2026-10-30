<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_an_empty_database_without_creating_users(): void
    {
        $admin = User::factory()->create(['email' => 'admin@vetflow.test']);

        $this->artisan('app:seed-demo')->assertSuccessful();

        $this->assertSame(ProductSeeder::TOTAL, Product::count());
        $this->assertTrue(Producer::exists());
        $this->assertSame(1, User::count());
        $this->assertTrue(Sale::exists());
        $this->assertSame([$admin->id], StockMovement::whereNotNull('user_id')->pluck('user_id')->unique()->values()->all());
    }

    public function test_it_does_not_duplicate_data_on_the_next_deploy(): void
    {
        User::factory()->create();

        $this->artisan('app:seed-demo')->assertSuccessful();
        $this->artisan('app:seed-demo')->assertSuccessful();

        $this->assertSame(ProductSeeder::TOTAL, Product::count());
    }

    public function test_it_requires_a_user(): void
    {
        $this->artisan('app:seed-demo')->assertFailed();

        $this->assertSame(0, Product::count());
    }
}
