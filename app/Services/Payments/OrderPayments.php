<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Mail\OrderPlaced;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * Records what happened to an order's payment. Stripe can report the same
 * payment twice (the customer returning to the site, then the webhook), so
 * every step here is safe to repeat: the first one counts, the rest are no-ops.
 */
class OrderPayments
{
    public const CARD = 'card';

    public const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];

    public function markPaid(Order $order, ?string $paymentIntentId = null): Order
    {
        $justPaid = DB::transaction(function () use ($order, $paymentIntentId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === 'paid') {
                return false;
            }

            $order->forceFill([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'stripe_payment_intent_id' => $paymentIntentId ?? $order->stripe_payment_intent_id,
            ]);

            /* Paid card orders go straight into the workroom */
            if ($order->status === OrderStatus::Pending) {
                $order->status = OrderStatus::Confirmed;
            }

            $order->save();

            return true;
        });

        $order->refresh();

        if ($justPaid) {
            $this->sendConfirmation($order);
        }

        return $order;
    }

    /** The Checkout page expired or the payment was declined: the order waits, and can be paid again. */
    public function markFailed(Order $order): Order
    {
        if ($order->payment_status === 'pending') {
            $order->forceFill(['payment_status' => 'failed'])->save();
        }

        return $order;
    }

    public function markRefunded(Order $order): Order
    {
        if ($order->payment_status === 'paid') {
            $order->forceFill(['payment_status' => 'refunded'])->save();
        }

        return $order;
    }

    /** The order email, sent once whichever way the order was paid. */
    public function sendConfirmation(Order $order): void
    {
        if ($order->confirmation_sent) {
            return;
        }

        rescue(function () use ($order) {
            Mail::to($order->email)->send(new OrderPlaced($order));
            $order->forceFill(['confirmation_sent' => true])->saveQuietly();
        });
    }
}
