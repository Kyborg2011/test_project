<?php

declare(strict_types=1);

use App\Http\Controllers\FileController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [UploadController::class, 'create'])->name('upload.create');

Route::get('/files', [FileController::class, 'index'])->name('files.index');
Route::post('/files', [UploadController::class, 'store'])->name('files.store');
Route::get('/files/{storedFile}/download', [FileController::class, 'download'])->name('files.download');
Route::delete('/files/{storedFile}', [FileController::class, 'destroy'])->name('files.destroy');
