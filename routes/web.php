<?php

use App\Http\Controllers\AccountPdfController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CashAuditExportController;
use App\Http\Controllers\InventoryExportController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Profile;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Erp\Categories;
use App\Livewire\Erp\DailyAudit;
use App\Livewire\Erp\InventoryCatalog;
use App\Livewire\Erp\SupplierDetail;
use App\Livewire\Erp\Suppliers;
use App\Livewire\Erp\UserManagement;
use App\Livewire\Pos\PosDesk;
use App\Livewire\Pos\PosLock;
use App\Livewire\Pos\TerminalSetup;
use App\Services\PosTerminalService;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/registro', Register::class)->name('register');
    Route::get('/recuperar-contrasena', ForgotPassword::class)->name('password.request');
    Route::get('/restablecer-contrasena/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// A terminal opens the mostrador; anywhere else, the login or the user's home.
Route::get('/', function (PosTerminalService $terminals) {
    if ($terminals->current()) {
        return redirect()->route('pos.desk');
    }

    return auth()->check() ? redirect(auth()->user()->homeUrl()) : redirect()->route('login');
})->name('home');

// PC 1: Mostrador. Works without login on a PC enabled as terminal: the user is the one who opened the caja.
Route::get('/pos/habilitar', TerminalSetup::class)->name('pos.setup');

Route::middleware('terminal')->group(function () {
    Route::get('/pos', PosDesk::class)->name('pos.desk');
    Route::get('/pos/caja', PosLock::class)->name('pos.lock');
    Route::get('/pos/remitos/{sale}/pdf', [AccountPdfController::class, 'posRemito'])->name('pos.remito-pdf');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/perfil', Profile::class)->name('profile');

    // PC 2: Administración
    Route::prefix('erp')->name('erp.')->middleware('can:admin')->group(function () {
        Route::redirect('/', '/erp/inventario');
        Route::get('/inventario', InventoryCatalog::class)->name('inventory');
        Route::get('/inventario/exportar', InventoryExportController::class)->name('inventory.export');
        Route::get('/cuentas-corrientes', AccountsReceivable::class)->name('accounts');
        Route::get('/cuentas-corrientes/{producer}/resumen.pdf', [AccountPdfController::class, 'statement'])->name('accounts.statement-pdf');
        Route::get('/cuentas-corrientes/{producer}/remitos.pdf', [AccountPdfController::class, 'remitosDetail'])->name('accounts.remitos-pdf');
        Route::get('/remitos/{sale}/pdf', [AccountPdfController::class, 'remito'])->name('remitos.pdf');
        Route::get('/pagos/{payment}/recibo.pdf', [AccountPdfController::class, 'receipt'])->name('payments.receipt-pdf');
        Route::get('/auditoria', DailyAudit::class)->name('audit');
        Route::get('/auditoria/exportar', CashAuditExportController::class)->name('audit.export');
        Route::get('/rubros', Categories::class)->name('categories');
        Route::get('/proveedores', Suppliers::class)->name('suppliers');
        Route::get('/proveedores/{supplier}', SupplierDetail::class)->name('suppliers.show');
        Route::redirect('/rubros-proveedores', '/erp/proveedores');
        Route::get('/usuarios', UserManagement::class)->name('users');
    });
});
