<?php

use App\Http\Controllers\InventoryExportController;
use App\Livewire\Auth\Login;
use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Erp\DailyAudit;
use App\Livewire\Erp\InventoryCatalog;
use App\Livewire\Pos\PosDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/pos');

    // PC 1: Mostrador
    Route::get('/pos', PosDesk::class)->name('pos.desk');

    // PC 2: Administración
    Route::prefix('erp')->name('erp.')->group(function () {
        Route::redirect('/', '/erp/inventario');
        Route::get('/inventario', InventoryCatalog::class)->name('inventory');
        Route::get('/inventario/exportar', InventoryExportController::class)->name('inventory.export');
        Route::get('/cuentas-corrientes', AccountsReceivable::class)->name('accounts');
        Route::get('/auditoria', DailyAudit::class)->name('audit');
    });
});
