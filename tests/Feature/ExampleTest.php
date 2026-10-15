<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/erp/inventario')->assertRedirect('/login');
    }

    public function test_the_root_redirects_authenticated_users_to_the_pos(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/pos');
    }
}
