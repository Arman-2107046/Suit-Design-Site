<?php

namespace Tests\Feature;

use App\Filament\Widgets\AtelierStats;
use App\Filament\Widgets\CustomerMap;
use App\Filament\Widgets\DesignTrends;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrderPipeline;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\SupportInbox;
use App\Filament\Widgets\TopFabrics;
use App\Filament\Widgets\WelcomeBanner;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Admin;
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
            'shipping' => ['name' => 'Alex Morgan', 'phone' => '', 'address' => 'A', 'address2' => null, 'city' => 'C', 'postcode' => 'P', 'country' => 'Bangladesh'],
            'body_profile' => ['name' => 'P', 'height_cm' => 180, 'weight_kg' => 80, 'age' => 30, 'measurements' => []],
        ]);
        $order->items()->create(['fabric_id' => $fabric->id, 'fabric_name' => $fabric->name, 'fabric_image' => $fabric->image, 'price' => 249, 'quantity' => 2, 'design' => [], 'summary' => []]);

        return $order;
    }

    public function test_dashboard_widgets_render_with_data(): void
    {
        $this->actingAs(Admin::factory()->create(['name' => 'Arman Rafi']), 'admin');
        $order = $this->seedOrder();
        ContactMessage::create(['name' => 'Sam', 'email' => 'sam@example.test', 'subject' => 'Fit', 'message' => 'Hello there']);

        Livewire::test(WelcomeBanner::class)->assertSee('Arman')->assertSee('1 order to confirm')->assertSee('1 unread message');
        Livewire::test(AtelierStats::class)->assertSee('Revenue this month')->assertSee('$249')->assertSee('Orders this month')->assertSee('Inbox');
        Livewire::test(SalesOverview::class)->assertSee('Sales overview')->assertSee('$249')->assertSee('No sales in '.(now()->year - 1));
        Livewire::test(OrderPipeline::class)->assertSee('Order pipeline')->assertSee('1 live')->assertSee('Pending');
        Livewire::test(DesignTrends::class)->assertSee('Design trends')->assertSee('Standard lining');
        Livewire::test(TopFabrics::class)->assertSee('Navy Twill')->assertSee('$498 revenue');
        Livewire::test(CustomerMap::class)->assertSee('Bangladesh')->assertSee('1 country')->assertSee('jsvectormap.min.js');
        Livewire::test(LatestOrders::class)->assertSee($order->number)->assertSee('Alex Morgan');
        Livewire::test(SupportInbox::class)->assertSee('Sam')->assertSee('Hello there');
    }

    /*
     * Livewire attaches a component to its first element, and Filament loads
     * dashboard widgets lazily — so a widget with two top-level elements is
     * swapped in as only the first, and the rest silently disappears. That is
     * how the customer map once vanished behind its own <style> tag.
     */
    public function test_every_dashboard_widget_renders_as_a_single_element(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->seedOrder();

        $widgets = collect(\Filament\Facades\Filament::getPanel('admin')->getWidgets());

        $this->assertNotEmpty($widgets);

        foreach ($widgets as $widget) {
            $html = Livewire::test($widget)->html();

            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');

            $roots = collect($dom->getElementsByTagName('body')->item(0)->childNodes)
                ->filter(fn ($node) => $node->nodeType === XML_ELEMENT_NODE)
                ->count();

            $this->assertSame(1, $roots, class_basename($widget).' has '.$roots.' top-level elements');
        }
    }

    public function test_dashboard_widgets_render_when_empty(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(WelcomeBanner::class)->assertSee('Nothing is waiting on you');
        Livewire::test(AtelierStats::class)->assertSee('$0');
        Livewire::test(SalesOverview::class)->assertSee('$0');
        Livewire::test(OrderPipeline::class)->assertSee('0 live');
        Livewire::test(DesignTrends::class)->assertSee('No designs yet');
        Livewire::test(TopFabrics::class)->assertSee('No orders yet');
        Livewire::test(CustomerMap::class)->assertSee('No customers on the map yet');
        Livewire::test(LatestOrders::class)->assertOk();
        Livewire::test(SupportInbox::class)->assertSee('Inbox zero');
    }
}
