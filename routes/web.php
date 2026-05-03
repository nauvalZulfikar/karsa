<?php

use App\Http\Controllers\DokumenController;
use App\Http\Controllers\ImportTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dokumen/{dokumen}/download', [DokumenController::class, 'download'])->name('dokumen.download');
    Route::get('/import/template/{type}', [ImportTemplateController::class, 'download'])->name('import.template');
});
