<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReportController;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

require __DIR__.'/auth.php';

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Products
        Route::resource('products', ProductController::class);
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])
            ->name('products.restore');

        // Product Import
        Route::get('products-import', [ProductImportController::class, 'form'])
            ->name('products.import');
        Route::post('products-import', [ProductImportController::class, 'import'])
            ->name('products.import.store');
        Route::get('products-import/template', [ProductImportController::class, 'downloadTemplate'])
            ->name('products.import.template');

        // Other resources
        Route::resource('categories', CategoryController::class);
        Route::resource('orders', OrderController::class)->only(['index', 'show', 'update']);
        Route::resource('users', UserController::class)->only(['index', 'show', 'destroy']);

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
