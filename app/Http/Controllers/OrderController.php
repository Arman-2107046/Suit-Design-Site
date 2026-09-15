<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $isGuestOwner = in_array($order->id, $request->session()->get('guest_orders', []), true);

        abort_unless($isOwner || $isGuestOwner, 404);

        return Inertia::render('Orders/Show', [
            'order' => $order->load('items')->toCustomerArray(),
            'placed' => (bool) $request->session()->get('placed', false),
        ]);
    }
}
