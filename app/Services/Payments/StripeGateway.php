<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/*
 * Everything that talks to Stripe. Kept to a few calls so tests can stand in
 * for it, and so the rest of the app never handles card details: customers
 * pay on Stripe's own page, and Stripe tells us the result.
 */
class StripeGateway
{
    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $publishableKey,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.stripe.secret'),
            config('services.stripe.key'),
        );
    }

    /** Both keys are in place: checkout can offer card payments. */
    public function enabled(): bool
    {
        return filled($this->secret) && filled($this->publishableKey);
    }

    /** "test" or "live", read from the key itself, so the admin can see which money is real. */
    public function mode(): ?string
    {
        if (blank($this->secret)) {
            return null;
        }

        return str_starts_with($this->secret, 'sk_live_') || str_starts_with($this->secret, 'rk_live_') ? 'live' : 'test';
    }

    /**
     * A hosted Checkout page for an order. The order number travels as
     * metadata and the client reference, so the webhook can find the order
     * even if the customer never comes back to the site.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): array
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->email,
            'client_reference_id' => $order->number,
            'metadata' => ['order_number' => $order->number, 'order_id' => (string) $order->id],
            'payment_intent_data' => [
                'description' => "Order {$order->number}",
                'metadata' => ['order_number' => $order->number, 'order_id' => (string) $order->id],
            ],
            'line_items' => $order->items->map(fn (OrderItem $item) => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => strtolower($order->currency ?: 'usd'),   // the shop prices in USD
                    'unit_amount' => self::toMinorUnits($item->price),
                    'product_data' => array_filter([
                        'name' => Str::limit("Made-to-measure suit · {$item->fabric_name}", 250),
                        'description' => Str::limit(collect($item->summary)->map(fn ($row) => ($row['label'] ?? '').': '.($row['value'] ?? ''))->implode(' · '), 500) ?: null,
                        'images' => filled($item->fabric_image) && str_starts_with($item->fabric_image, 'https://') ? [$item->fabric_image] : null,
                    ]),
                ],
            ])->values()->all(),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            /* An unpaid page should not linger for a day: the order can always start a fresh one */
            'expires_at' => now()->addMinutes(60)->timestamp,
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /**
     * Where a Checkout Session stands.
     *
     * @return array{status: ?string, payment_status: ?string, payment_intent: ?string, order_number: ?string}
     */
    public function retrieveSession(string $sessionId): array
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId);

        return [
            'status' => $session->status,
            'payment_status' => $session->payment_status,
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent?->id,
            'order_number' => $session->metadata['order_number'] ?? $session->client_reference_id,
        ];
    }

    /** A harmless call that proves the secret key works. */
    public function check(): void
    {
        $this->client()->balance->retrieve();
    }

    /** Where this payment lives in the Stripe dashboard. */
    public function dashboardUrl(string $paymentIntentId): string
    {
        return 'https://dashboard.stripe.com/'.($this->mode() === 'test' ? 'test/' : '').'payments/'.$paymentIntentId;
    }

    /** 249.50 → 24950. Every currency we sell in has two decimals. */
    public static function toMinorUnits(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->secret,
            'max_network_retries' => 2,
        ]);
    }
}
