<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'cuentas corrientes' => ['erp.accounts'],
            'auditoría' => ['erp.audit'],
            'rubros y proveedores' => ['erp.catalogs'],
            'exportación CSV del inventario' => ['erp.inventory.export'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_a_guest_is_redirected_to_the_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_pos_outside_a_terminal_asks_to_enable_the_pc(): void
    {
        $this->get(route('pos.desk'))->assertRedirect(route('pos.setup'));
        $this->get(route('pos.lock'))->assertRedirect(route('pos.setup'));
        $this->withoutVite()->get(route('pos.setup'))->assertOk()->assertSee('Ingresar como administrador');
    }
}
