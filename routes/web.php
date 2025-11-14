<?php

use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\TenantSelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Tenant selection endpoints (backend infrastructure for Filament)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('tenants', [TenantSelectionController::class, 'index'])->name('tenants.index');
    Route::post('tenants/select', [TenantSelectionController::class, 'select'])->name('tenants.select');

    // Lightweight tenancy check used by tests and health checks.
    Route::get('__tenancy-check', function () {
        return response()->json([
            'initialized' => tenancy()->initialized,
            'tenant_id' => optional(tenancy()->tenant)->getTenantKey(),
        ]);
    })->name('tenancy.check');
    // Tenant-scoped demo CRUD used by integration tests and quick verification.
    Route::get('/tenant/customers', [CustomerController::class, 'index'])->name('tenant.customers.index');
    Route::post('/tenant/customers', [CustomerController::class, 'store'])->name('tenant.customers.store');
});
