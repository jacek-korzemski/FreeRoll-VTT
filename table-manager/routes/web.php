<?php

use App\Http\Controllers\AdminDownloadController;
use App\Http\Controllers\AdminLoginController;
use App\Livewire\Admin\Analytics;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\FilesIndex;
use App\Livewire\Admin\TableShow;
use App\Livewire\Admin\TablesIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::get('login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('login', [AdminLoginController::class, 'store'])->middleware('throttle:8,1')->name('login.store');
    });

    Route::post('logout', [AdminLoginController::class, 'destroy'])->middleware('admin.auth')->name('logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('tables', TablesIndex::class)->name('tables');
        Route::get('tables/{table}', TableShow::class)->name('tables.show');
        Route::get('files', FilesIndex::class)->name('files');
        Route::get('analytics', Analytics::class)->name('analytics');
        Route::get('tables/{table}/download/{kind}', [AdminDownloadController::class, 'json'])
            ->whereIn('kind', ['state', 'rolls'])
            ->name('download.json');
        Route::get('tables/{table}/asset', [AdminDownloadController::class, 'asset'])->name('download.asset');
    });
});

require __DIR__.'/auth.php';
