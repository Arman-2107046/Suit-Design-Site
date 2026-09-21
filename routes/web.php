<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use App\Models\BlogPost;
use App\Models\HomepageSetting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Api\SuitConfiguratorController;

Route::get('/', fn () => Inertia::render('Home', [
    'homepage' => HomepageSetting::current()->toHomepageProps(),
    'stories' => BlogPost::published()->with(['category', 'author'])->withCount(['likes', 'approvedComments'])->orderByDesc('published_at')->limit(3)->get()->map->toCardArray(),
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

Route::get('/contact', [SupportController::class, 'contact'])->name('contact');
Route::post('/contact', [SupportController::class, 'sendMessage'])->middleware('throttle:10,1')->name('contact.send');
Route::get('/samples', [SupportController::class, 'samples'])->name('samples');
Route::post('/samples', [SupportController::class, 'requestSamples'])->middleware('throttle:10,1')->name('samples.request');
Route::get('/track', [SupportController::class, 'track'])->name('track');
Route::post('/track', [SupportController::class, 'lookup'])->middleware('throttle:20,1')->name('track.lookup');
Route::get('/faqs', [SupportController::class, 'faqs'])->name('faqs');
Route::get('/p/{page}', [SupportController::class, 'page'])->name('page');

Route::get('/journal', [JournalController::class, 'index'])->name('journal');
Route::get('/journal/feed', [JournalController::class, 'feed'])->name('journal.feed');
Route::get('/journal/{post}', [JournalController::class, 'show'])->name('journal.show');
Route::post('/journal/{post}/comments', [JournalController::class, 'comment'])->middleware('throttle:10,1')->name('journal.comment');
Route::post('/journal/{post}/like', [JournalController::class, 'like'])->middleware('throttle:60,1')->name('journal.like');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';



// for /f "tokens=5" %a in ('netstat -ano ^| findstr :3306') do taskkill /F /PID %a
