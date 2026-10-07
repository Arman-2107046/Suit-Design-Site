<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\OrderPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /** The events this endpoint acts on. The admin's Stripe page lists them for setting up the endpoint. */
    public const EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
    ];

    public function __construct(private readonly OrderPayments $payments) {}

    /**
     * Stripe telling us what happened to a payment. Every request is checked
     * against the endpoint's signing secret, so nobody else can mark an order
     * paid. Stripe retries anything that does not get a 2xx, so an event we
     * do not need is still acknowledged.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            Log::warning('Stripe webhook received, but STRIPE_WEBHOOK_SECRET is not set.');

            return response()->json(['message' => 'Webhook signing secret not configured.'], 503);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed' => $object->payment_status === 'paid' ? $this->paid($object) : null,
            'checkout.session.async_payment_succeeded' => $this->paid($object),
            'checkout.session.async_payment_failed', 'checkout.session.expired' => $this->failed($object),
            'charge.refunded' => $this->refunded($object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function paid(object $session): void
    {
        if ($order = $this->orderForSession($session)) {
            $this->payments->markPaid($order, is_string($session->payment_intent ?? null) ? $session->payment_intent : null);
        }
    }

    private function failed(object $session): void
    {
        $order = $this->orderForSession($session);

        /* Only the order's current attempt: an old expired page must not undo a newer one */
        if ($order && $order->stripe_session_id === $session->id) {
            $this->payments->markFailed($order);
        }
    }

    private function refunded(object $charge): void
    {
        /* Partial refunds leave the order paid; the full amount back marks it refunded */
        if (! ($charge->refunded ?? false) || ! is_string($charge->payment_intent ?? null)) {
            return;
        }

        if ($order = Order::query()->where('stripe_payment_intent_id', $charge->payment_intent)->first()) {
            $this->payments->markRefunded($order);
        }
    }

    private function orderForSession(object $session): ?Order
    {
        $number = $session->metadata->order_number ?? $session->client_reference_id ?? null;

        return Order::query()
            ->when($number, fn ($q) => $q->where('number', $number), fn ($q) => $q->where('stripe_session_id', $session->id))
            ->first();
    }
}
