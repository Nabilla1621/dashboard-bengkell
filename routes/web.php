<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PartBahanImportController;
use App\Http\Controllers\ImportBatchController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/import-part-bahan', [PartBahanImportController::class, 'form'])
    ->name('part-bahan.form');
Route::get('/import/riwayat', [ImportBatchController::class, 'index'])->name('import-batches.index');

Route::post('/import-pkb', [ImportController::class, 'importPkb']);
Route::post('/import-faktur', [ImportController::class, 'importFaktur']);
Route::post('/import-part-bahan', [PartBahanImportController::class, 'store'])
    ->name('part-bahan.store');

Route::delete('/import/riwayat/{batch}', [ImportBatchController::class, 'destroy'])->name('import-batches.destroy');