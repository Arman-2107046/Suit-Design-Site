<?php

use App\Http\Controllers\ProfileController;
use App\Models\HomepageSetting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\SuitConfiguratorController;

Route::get('/', fn () => Inertia::render('Home', [
    'homepage' => HomepageSetting::current()->toHomepageProps(),
]))->name('home');

Route::get('/design', fn () => Inertia::render('Welcome'))->name('design');

Route::get('/design-suit', [SuitConfiguratorController::class, 'index'])
    ->name('suit.configurator');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';



// for /f "tokens=5" %a in ('netstat -ano ^| findstr :3306') do taskkill /F /PID %a
