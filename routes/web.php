<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
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
    return Inertia::render('Dashboard', [
        'orders' => request()->user()->orders()->with('items')->get()->map->toCustomerArray(),
        'profiles' => request()->user()->bodyProfiles()->latest()->get()->map->toSnapshot(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/cart', [CheckoutController::class, 'cart'])->name('cart');
Route::get('/checkout/body-profile', [CheckoutController::class, 'bodyProfile'])->name('checkout.body-profile');
Route::post('/checkout/estimate', [CheckoutController::class, 'estimate'])->name('checkout.estimate');
Route::post('/checkout/body-profile', [CheckoutController::class, 'storeBodyProfile'])->middleware('auth')->name('checkout.body-profile.store');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';



// for /f "tokens=5" %a in ('netstat -ano ^| findstr :3306') do taskkill /F /PID %a
