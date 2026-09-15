<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Mail\OrderPlaced;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fabric(): Fabric
    {
        return Fabric::create(['name' => 'Navy Twill', 'price' => 249, 'image' => 'https://example.test/navy.png', 'is_default' => true, 'status' => true]);
    }

    private function payload(Fabric $fabric, array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'Guest@Example.com',
            'items' => [[
                'fabric_id' => $fabric->id,
                'quantity' => 1,
                'design' => ['body' => ['id' => 1, 'name' => 'Single-breasted']],
                'summary' => [['label' => 'Body', 'value' => 'Single-breasted'], ['label' => 'Lining', 'value' => 'Default']],
            ]],
            'body_profile' => [
                'name' => 'My profile',
                'height_cm' => 182,
                'weight_kg' => 95,
                'age' => 50,
                'measurements' => [
                    'sleeve_length' => 66, 'shoulder_width' => 51.5, 'chest' => 112.5, 'stomach' => 105, 'hips' => 110.5, 'neck' => 43,
                    'torso_length' => 79.5, 'bicep' => 36.5, 'leg_length' => 106, 'pants_waist' => 102, 'thigh' => 63.5, 'rise' => 70.5,
                ],
            ],
            'shipping' => ['name' => 'Alex Morgan', 'phone' => '+880 1700 000000', 'address' => '12 Gulshan Ave', 'address2' => '', 'city' => 'Dhaka', 'postcode' => '1212', 'country' => 'Bangladesh'],
            'payment_method' => 'cash_on_delivery',
            'notes' => '',
        ], $overrides);
    }

    public function test_estimate_returns_measurements(): void
    {
        $this->postJson('/checkout/estimate', ['height' => 182, 'weight' => 95, 'age' => 50])
            ->assertOk()
            ->assertJsonPath('measurements.chest', 112.5)
            ->assertJsonPath('measurements.shoulder_width', 52);
    }

    public function test_guest_can_place_an_order_and_open_the_receipt(): void
    {
        Mail::fake();
        $fabric = $this->fabric();

        $response = $this->post('/checkout', $this->payload($fabric));

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('guest@example.com', $order->email);
        $this->assertNull($order->user_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertEquals(249, (float) $order->total);
        $this->assertStringStartsWith('CT-', $order->number);
        $this->assertSame('Navy Twill', $order->items->first()->fabric_name);
        $this->assertSame(112.5, (float) $order->body_profile['measurements']['chest']);

        $this->get(route('orders.show', $order))->assertOk();

        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo('guest@example.com') && $mail->order->is($order));
    }

    public function test_receipt_is_hidden_from_strangers(): void
    {
        $fabric = $this->fabric();
        $this->post('/checkout', $this->payload($fabric));
        $order = Order::first();

        $this->flushSession();
        $this->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_price_comes_from_the_catalogue_not_the_client(): void
    {
        $fabric = $this->fabric();

        $this->post('/checkout', $this->payload($fabric, ['items' => [['quantity' => 2, 'price' => 1]]]));

        $this->assertEquals(498, (float) Order::first()->total);
    }

    public function test_inactive_fabric_is_rejected(): void
    {
        $fabric = $this->fabric();
        $fabric->update(['status' => false]);

        $this->post('/checkout', $this->payload($fabric))->assertSessionHasErrors('items.0.fabric_id');
        $this->assertSame(0, Order::count());
    }

    public function test_logged_in_orders_show_on_the_dashboard_and_registration_adopts_guest_orders(): void
    {
        $fabric = $this->fabric();
        $this->post('/checkout', $this->payload($fabric, ['email' => 'new@example.com']));

        $this->post('/register', ['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'password-123', 'password_confirmation' => 'password-123']);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($user->id, Order::first()->user_id);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->has('orders', 1)->where('orders.0.status.value', 'pending'));
    }

    public function test_logged_in_user_can_save_a_body_profile(): void
    {
        $user = User::factory()->create();
        $profile = $this->payload($this->fabric())['body_profile'];

        $this->actingAs($user)->post('/checkout/body-profile', $profile)->assertRedirect();

        $this->assertSame(1, $user->bodyProfiles()->count());
        $this->assertSame(182, $user->bodyProfiles()->first()->height_cm);
    }

    public function test_status_change_stamps_shipping_dates(): void
    {
        $fabric = $this->fabric();
        $this->post('/checkout', $this->payload($fabric));
        $order = Order::first();

        $order->update(['status' => OrderStatus::Shipped]);
        $this->assertNotNull($order->fresh()->shipped_at);

        $order->update(['status' => OrderStatus::Delivered]);
        $this->assertNotNull($order->fresh()->delivered_at);
    }
}
