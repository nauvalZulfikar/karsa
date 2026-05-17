<?php

use App\Http\Controllers\DokumenController;
use App\Http\Controllers\ImportTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dokumen/{dokumen}/download', [DokumenController::class, 'download'])->name('dokumen.download');
    Route::get('/dokumen/download/{encodedPath}', [DokumenController::class, 'downloadGenerated'])->name('dokumen.download.generated');
    Route::get('/import/template/{type}', [ImportTemplateController::class, 'download'])->name('import.template');
});
