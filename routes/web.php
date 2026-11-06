<?php

declare(strict_types=1);

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [UploadController::class, 'create'])->name('upload.create');
Route::post('/files', [UploadController::class, 'store'])->name('files.store');
