<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


use App\Http\Controllers\Api\SuitConfiguratorController;

Route::get('/configurator', [SuitConfiguratorController::class, 'index']);


/* RankYak publishes its articles here. The token is the key: see the RankYak page in the admin. */
Route::post('/webhooks/rankyak/{token}', \App\Http\Controllers\RankYakWebhookController::class)
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:60,1')
    ->name('webhooks.rankyak');

/* Stripe reports payments here. Every request is verified against STRIPE_WEBHOOK_SECRET. */
Route::post('/webhooks/stripe', \App\Http\Controllers\StripeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.stripe');
