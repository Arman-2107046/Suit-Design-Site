<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\StripePayments;
use App\Mail\OrderPlaced;
use App\Models\Admin;
use App\Models\Fabric;
use App\Models\Order;
use App\Services\Payments\StripeGateway;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class StripePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    /** Stands in for Stripe: records the sessions it "creates" and answers lookups from a script. */
    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.stripe.key' => 'pk_test_x', 'services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->fake = new class('sk_test_x', 'pk_test_x') extends StripeGateway
        {
            public array $created = [];

            public array $sessions = [];

            public function createCheckoutSession(Order $order, string $successUrl, string $cancelUrl): array
            {
                $id = 'cs_test_'.(count($this->created) + 1);
                $this->created[] = compact('id', 'successUrl', 'cancelUrl') + ['order' => $order->number, 'items' => $order->items->count()];

                return ['id' => $id, 'url' => "https://checkout.stripe.com/c/pay/{$id}"];
            }

            public function retrieveSession(string $sessionId): array
            {
                return $this->sessions[$sessionId] ?? ['status' => 'open', 'payment_status' => 'unpaid', 'payment_intent' => null, 'order_number' => null];
            }
        };
        $this->app->instance(StripeGateway::class, $this->fake);
    }

    private function fabric(): Fabric
    {
        return Fabric::create(['name' => 'Navy Twill', 'price' => 249.5, 'image' => 'https://example.test/navy.png', 'is_default' => true, 'status' => true]);
    }

    private function payload(Fabric $fabric, string $method = 'card'): array
    {
        return [
            'email' => 'guest@example.com',
            'items' => [[
                'fabric_id' => $fabric->id, 'quantity' => 2,
                'design' => ['body' => ['id' => 1, 'name' => 'Single-breasted']],
                'summary' => [['label' => 'Body', 'value' => 'Single-breasted']],
            ]],
            'body_profile' => [
                'name' => 'Me', 'height_cm' => 182, 'weight_kg' => 95, 'age' => 50,
                'measurements' => [
                    'sleeve_length' => 66, 'shoulder_width' => 51.5, 'chest' => 112.5, 'stomach' => 105, 'hips' => 110.5, 'neck' => 43,
                    'torso_length' => 79.5, 'bicep' => 36.5, 'leg_length' => 106, 'pants_waist' => 102, 'thigh' => 63.5, 'rise' => 70.5,
                ],
            ],
            'shipping' => ['name' => 'Alex Morgan', 'phone' => '+880 1700 000000', 'address' => '12 Gulshan Ave', 'address2' => '', 'city' => 'Dhaka', 'postcode' => '1212', 'country' => 'Bangladesh'],
            'payment_method' => $method,
            'notes' => '',
        ];
    }

    /** Places a card order as a guest and returns it, already sent to Stripe. */
    private function cardOrder(): Order
    {
        $this->post('/checkout', $this->payload($this->fabric()))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        return Order::sole();
    }

    private function webhook(array $event, ?string $secret = self::WEBHOOK_SECRET)
    {
        $payload = json_encode($event);
        $time = time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', "{$time}.{$payload}", $secret);

        return $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function event(string $type, array $object): array
    {
        return ['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]];
    }

    public function test_without_keys_checkout_offers_no_card_and_refuses_one(): void
    {
        config(['services.stripe.key' => null, 'services.stripe.secret' => null]);
        $this->app->instance(StripeGateway::class, StripeGateway::fromConfig());

        $this->get('/checkout')->assertInertia(fn ($page) => $page->where('paymentMethods', ['cash_on_delivery', 'bank_transfer']));
        $this->post('/checkout', $this->payload($this->fabric()))->assertSessionHasErrors('payment_method');
    }

    public function test_with_keys_card_comes_first_and_leads_to_stripe(): void
    {
        $this->get('/checkout')->assertInertia(fn ($page) => $page->where('paymentMethods', ['card', 'cash_on_delivery', 'bank_transfer']));

        $order = $this->cardOrder();

        $this->assertSame('card', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('cs_test_1', $order->stripe_session_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(1, $this->fake->created[0]['items']);
        $this->assertStringContainsString("/checkout/stripe/{$order->number}/success?session_id={CHECKOUT_SESSION_ID}", $this->fake->created[0]['successUrl']);

        Mail::assertNothingSent();   // the confirmation waits for the payment
    }

    public function test_coming_back_paid_confirms_the_order_and_emails_once(): void
    {
        $order = $this->cardOrder();
        $this->fake->sessions['cs_test_1'] = ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_123', 'order_number' => $order->number];

        $this->get(route('checkout.stripe.success', $order).'?session_id=cs_test_1')
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('payment', 'paid');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('pi_123', $order->stripe_payment_intent_id);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->paid_at);

        /* The webhook for the same payment arrives afterwards: nothing changes, no second email */
        $this->webhook($this->event('checkout.session.completed', [
            'id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_intent' => 'pi_123',
            'client_reference_id' => $order->number, 'metadata' => ['order_number' => $order->number],
        ]))->assertOk();

        Mail::assertSent(OrderPlaced::class, 1);
    }

    public function test_a_session_id_that_is_not_the_orders_changes_nothing(): void
    {
        $order = $this->cardOrder();
        $this->fake->sessions['cs_other'] = ['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_x', 'order_number' => $order->number];

        $this->get(route('checkout.stripe.success', $order).'?session_id=cs_other')->assertRedirect(route('orders.show', $order));

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_someone_elses_order_cannot_be_opened_or_paid(): void
    {
        $order = $this->cardOrder();
        $this->flushSession();

        $this->get(route('checkout.stripe.success', $order).'?session_id=cs_test_1')->assertNotFound();
        $this->post(route('orders.pay', $order))->assertNotFound();
    }

    public function test_a_cancelled_payment_can_be_paid_again_from_the_order_page(): void
    {
        $order = $this->cardOrder();

        $this->get(route('checkout.stripe.cancel', $order))->assertRedirect(route('orders.show', $order))->assertSessionHas('payment', 'cancelled');
        $this->get(route('orders.show', $order))->assertInertia(fn ($page) => $page->where('canPay', true));

        $this->post(route('orders.pay', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_2');
        $this->assertSame('cs_test_2', $order->fresh()->stripe_session_id);
    }

    public function test_the_webhook_marks_an_order_paid_even_if_the_customer_never_returns(): void
    {
        $order = $this->cardOrder();

        $this->webhook($this->event('checkout.session.completed', [
            'id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_intent' => 'pi_777',
            'client_reference_id' => $order->number, 'metadata' => ['order_number' => $order->number],
        ]))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pi_777', $order->fresh()->stripe_payment_intent_id);
        Mail::assertSent(OrderPlaced::class, 1);

        /* A full refund made in the Stripe dashboard */
        $this->webhook($this->event('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'refunded' => true, 'payment_intent' => 'pi_777']))->assertOk();
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_an_expired_page_marks_the_attempt_failed_but_only_the_current_one(): void
    {
        $order = $this->cardOrder();

        $this->webhook($this->event('checkout.session.expired', ['id' => 'cs_old', 'object' => 'checkout.session', 'metadata' => ['order_number' => $order->number]]))->assertOk();
        $this->assertSame('pending', $order->fresh()->payment_status, 'an older page expiring changes nothing');

        $this->webhook($this->event('checkout.session.expired', ['id' => 'cs_test_1', 'object' => 'checkout.session', 'metadata' => ['order_number' => $order->number]]))->assertOk();
        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_the_webhook_rejects_anything_not_signed_by_stripe(): void
    {
        $order = $this->cardOrder();
        $event = $this->event('checkout.session.completed', ['id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'metadata' => ['order_number' => $order->number]]);

        $this->webhook($event, 'whsec_wrong')->assertStatus(400);
        $this->postJson(route('webhooks.stripe'), $event)->assertStatus(400);
        $this->assertSame('pending', $order->fresh()->payment_status);

        config(['services.stripe.webhook_secret' => null]);
        $this->webhook($event)->assertStatus(503);
    }

    public function test_offline_orders_are_still_confirmed_by_email_straight_away(): void
    {
        $this->post('/checkout', $this->payload($this->fabric(), 'cash_on_delivery'))->assertRedirect();

        Mail::assertSent(OrderPlaced::class, 1);
        $this->assertTrue(Order::sole()->confirmation_sent);
    }

    public function test_amounts_are_sent_to_stripe_in_cents(): void
    {
        $this->assertSame(24950, StripeGateway::toMinorUnits('249.50'));
        $this->assertSame(1999, StripeGateway::toMinorUnits(19.99));
    }

    public function test_the_admin_stripe_page_is_for_super_admins(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(StripePayments::getUrl())->assertForbidden();

        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');
        Livewire::test(StripePayments::class)
            ->assertSee('Accepting cards')
            ->assertSee('Test mode')
            ->assertSee(route('webhooks.stripe'))
            ->assertSee('checkout.session.completed');
    }
}
