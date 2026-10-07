<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/*
 * The customer's side of a Stripe payment: coming back from Stripe's page,
 * paid or not, and trying again. The webhook (StripeWebhookController) is
 * what Stripe uses to tell us directly; whichever arrives first marks the
 * order paid, and the other changes nothing.
 */
class StripeCheckoutController extends Controller
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly OrderPayments $payments,
    ) {}

    public function success(Request $request, Order $order): RedirectResponse
    {
        OrderController::authorizeView($request, $order);

        $sessionId = (string) $request->query('session_id');

        /* Only the session this order started counts, never one pasted into the address bar */
        if ($sessionId === '' || ! hash_equals((string) $order->stripe_session_id, $sessionId)) {
            return redirect()->route('orders.show', $order);
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('orders.show', $order)->with(['placed' => true, 'payment' => 'paid']);
        }

        try {
            $session = $this->stripe->retrieveSession($sessionId);
        } catch (Throwable $e) {
            report($e);

            /* Stripe will still tell us through the webhook; show it as on its way */
            return redirect()->route('orders.show', $order)->with(['placed' => true, 'payment' => 'processing']);
        }

        if ($session['payment_status'] === 'paid') {
            $this->payments->markPaid($order, $session['payment_intent']);

            return redirect()->route('orders.show', $order)->with(['placed' => true, 'payment' => 'paid']);
        }

        /* Some methods (bank debits) complete later; the webhook will confirm them */
        return redirect()->route('orders.show', $order)->with(['placed' => true, 'payment' => 'processing']);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        OrderController::authorizeView($request, $order);

        return redirect()->route('orders.show', $order)->with('payment', 'cancelled');
    }

    /** "Pay now" on the order page, after a cancelled, expired or failed attempt. */
    public function pay(Request $request, Order $order, CheckoutController $checkout): Response
    {
        OrderController::authorizeView($request, $order);

        abort_unless(
            $order->payment_method === OrderPayments::CARD
            && in_array($order->payment_status, ['pending', 'failed'], true)
            && $order->status->value !== 'cancelled'
            && $this->stripe->enabled(),
            409,
            'This order cannot be paid by card.'
        );

        return $checkout->startCardPayment($order);
    }
}
