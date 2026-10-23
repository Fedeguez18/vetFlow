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
            'mostrador POS' => ['pos.desk'],
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
}
