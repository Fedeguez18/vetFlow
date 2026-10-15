<?php

namespace Tests\Feature;

use App\Livewire\Pos\PosDesk;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tab_renders_with_the_demo_data(): void
    {
        $this->withoutVite();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@demo.test')->first());

        $this->get('/pos')->assertOk()->assertSee('Biológicos/Vacunas')->assertSee('Diego')->assertSee('Médico Veterinario');
        $this->get('/erp/inventario?orden=expiry')->assertOk()
            ->assertSeeInOrder(['Complejo Vitamínico ADE 500 ml', 'Vencido', 'Vacuna Anticarbunclosa Bovina', 'Crítico: 20 días', 'Ivermectina 3,15% Frasco 500 ml', 'Atención: 45 días']);
        $this->get('/erp/cuentas-corrientes')->assertOk()->assertSee('Estancia La Josefina');
        $this->get('/erp/auditoria')->assertOk()->assertSee('Total cobrado hoy');
    }

    public function test_each_terminal_renders_its_own_shell(): void
    {
        $this->withoutVite();
        $this->seed();
        $this->actingAs(User::first());

        $this->get('/pos')->assertOk()
            ->assertSee('Terminal Mostrador (PC 1)')
            ->assertDontSee('Cuentas Corrientes &amp; Remitos', false)
            ->assertDontSee(route('erp.inventory'));

        $this->get('/erp/cuentas-corrientes')->assertOk()
            ->assertSee('Terminal Gestión (PC 2)')
            ->assertSeeInOrder(['Inventario &amp; Lotes', 'Cuentas Corrientes &amp; Remitos', 'Auditoría &amp; Caja'], false)
            ->assertSee(route('pos.desk'));

        $this->get('/erp')->assertRedirect('/erp/inventario');
    }

    public function test_the_pos_sells_by_barcode_using_fefo(): void
    {
        $this->seed();
        $this->actingAs(User::first());

        $product = Product::where('barcode', '7798101000011')->first(); // Anticarbunclosa: lots of 10 (20 days) and 14
        $soonest = $product->lots()->orderBy('expiration_date')->first();

        Livewire::test(PosDesk::class)
            ->call('addByCode', '7798101000011')
            ->assertSet('alert', 'Escaneado: 7798101000011 — Vacuna Anticarbunclosa Bovina añadido al ticket.')
            ->call('confirm')
            ->assertDispatched('print-ticket');

        $this->assertSame(9, $soonest->fresh()->quantity);
        $this->assertSame(23, $product->fresh()->stock);
    }
}
