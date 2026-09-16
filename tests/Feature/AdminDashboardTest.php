<?php

namespace Tests\Feature;

use App\Filament\Widgets\AtelierStats;
use App\Filament\Widgets\FabricPopularity;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\SupportInbox;
use App\Filament\Widgets\WelcomeBanner;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrder(): Order
    {
        $fabric = Fabric::create(['name' => 'Navy Twill', 'price' => 249, 'image' => 'https://x/s.png', 'is_default' => true, 'status' => true]);
        $order = Order::create([
            'email' => 'buyer@example.test', 'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'pending',
            'subtotal' => 249, 'shipping_cost' => 0, 'total' => 249, 'currency' => 'USD',
            'shipping' => ['name' => 'Alex Morgan', 'phone' => '', 'address' => 'A', 'address2' => null, 'city' => 'C', 'postcode' => 'P', 'country' => 'X'],
            'body_profile' => ['name' => 'P', 'height_cm' => 180, 'weight_kg' => 80, 'age' => 30, 'measurements' => []],
        ]);
        $order->items()->create(['fabric_id' => $fabric->id, 'fabric_name' => $fabric->name, 'fabric_image' => $fabric->image, 'price' => 249, 'quantity' => 2, 'design' => [], 'summary' => []]);

        return $order;
    }

    public function test_dashboard_widgets_render_with_data(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Arman Rafi']));
        $order = $this->seedOrder();
        ContactMessage::create(['name' => 'Sam', 'email' => 'sam@example.test', 'subject' => 'Fit', 'message' => 'Hello there']);

        Livewire::test(WelcomeBanner::class)->assertSee('Arman')->assertSee('1 order to confirm')->assertSee('1 unread message');
        Livewire::test(AtelierStats::class)->assertSee('Revenue this month')->assertSee('$249')->assertSee('Orders this month')->assertSee('Inbox');
        Livewire::test(OrdersChart::class)->assertOk();
        Livewire::test(FabricPopularity::class)->assertOk();
        Livewire::test(LatestOrders::class)->assertSee($order->number)->assertSee('Alex Morgan');
        Livewire::test(SupportInbox::class)->assertSee('Sam')->assertSee('Hello there');
    }

    public function test_dashboard_widgets_render_when_empty(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WelcomeBanner::class)->assertSee('Nothing is waiting on you');
        Livewire::test(AtelierStats::class)->assertSee('$0');
        Livewire::test(OrdersChart::class)->assertOk();
        Livewire::test(FabricPopularity::class)->assertOk();
        Livewire::test(LatestOrders::class)->assertOk();
        Livewire::test(SupportInbox::class)->assertSee('Inbox zero');
    }
}
