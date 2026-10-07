<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Request $request, Order $order, StripeGateway $stripe): Response
    {
        self::authorizeView($request, $order);

        return Inertia::render('Orders/Show', [
            'order' => $order->load('items')->toCustomerArray(),
            'placed' => (bool) $request->session()->get('placed', false),
            /* What just happened at Stripe, if anything: paid, processing, cancelled, unavailable */
            'payment' => $request->session()->get('payment'),
            'canPay' => $order->payment_method === OrderPayments::CARD
                && in_array($order->payment_status, ['pending', 'failed'], true)
                && $order->status->value !== 'cancelled'
                && $stripe->enabled(),
        ]);
    }

    /** The customer who placed it, or the guest browser that placed it. Anyone else gets a 404. */
    public static function authorizeView(Request $request, Order $order): void
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $isGuestOwner = in_array($order->id, $request->session()->get('guest_orders', []), true);

        abort_unless($isOwner || $isGuestOwner, 404);
    }
}
