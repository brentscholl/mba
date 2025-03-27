<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/files', \App\Livewire\FilesIndex::class)->name('files.index');

Route::get('/files/{id}', [\App\Http\Controllers\FilesController::class, 'show'])->name('files.show');


