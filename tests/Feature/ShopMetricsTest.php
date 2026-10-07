<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Fabric;
use App\Models\Order;
use App\Services\Dashboard\ShopMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopMetricsTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function order(string $date, float $total, array $extra = []): Order
    {
        $order = Order::create(array_merge([
            'number' => 'CT-'.(++$this->n),
            'email' => "buyer{$this->n}@example.test",
            'status' => OrderStatus::Confirmed,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
            'currency' => 'USD',
            'shipping' => ['country' => 'Bangladesh'],
            'body_profile' => [],
        ], $extra));

        $order->forceFill(['created_at' => Carbon::parse($date)])->save();

        return $order;
    }

    /** Fabrics 1 and 2, which the order items point at. */
    private function fabrics(): void
    {
        foreach (['Navy Twill', 'Grey Flannel'] as $i => $name) {
            Fabric::create(['name' => $name, 'price' => 200, 'image' => 'f.png', 'is_default' => $i === 0, 'status' => true]);
        }
    }

    private function metrics(): ShopMetrics
    {
        return app(ShopMetrics::class);
    }

    public function test_revenue_lands_in_the_right_month_and_stops_at_today(): void
    {
        $this->order('2026-01-10', 100);
        $this->order('2026-01-20', 50);
        $this->order('2026-05-02', 200);
        $this->order('2026-03-03', 999, ['status' => OrderStatus::Cancelled]);

        $months = $this->metrics()->monthlyRevenue(2026);

        $this->assertSame(150.0, $months[0]);
        $this->assertSame(0.0, $months[2], 'cancelled orders are not revenue');
        $this->assertSame(200.0, $months[4]);
        $this->assertSame(0.0, $months[5], 'June is this month: shown, even at zero');
        $this->assertNull($months[6], 'July has not happened yet');
        $this->assertNull($months[11]);
    }

    public function test_a_past_year_has_all_twelve_months(): void
    {
        $this->order('2025-12-31 23:00:00', 80);

        $months = $this->metrics()->monthlyRevenue(2025);

        $this->assertCount(12, $months);
        $this->assertNotContains(null, $months);
        $this->assertSame(80.0, $months[11]);
    }

    public function test_this_year_is_compared_with_the_same_dates_last_year(): void
    {
        $this->order('2026-02-01', 300);
        $this->order('2025-02-01', 100);
        $this->order('2025-11-01', 5000, ['email' => 'late@example.test']);   // after 15 June: must not count

        $yoy = $this->metrics()->yearOnYear(2026);

        $this->assertSame(300.0, $yoy['total']);
        $this->assertSame(100.0, $yoy['previous']);
        $this->assertSame(200.0, $yoy['change']);
        $this->assertTrue($yoy['partial']);
    }

    public function test_a_year_with_nothing_before_it_has_no_percentage(): void
    {
        $this->order('2026-02-01', 300);

        $this->assertNull($this->metrics()->yearOnYear(2026)['change']);
    }

    public function test_the_pipeline_counts_every_stage_and_how_long_delivery_takes(): void
    {
        $this->order('2026-06-01', 100, ['status' => OrderStatus::Pending]);
        $this->order('2026-06-01', 100, ['status' => OrderStatus::InProduction]);
        $this->order('2026-06-01', 100, ['status' => OrderStatus::InProduction]);
        $this->order('2026-06-01', 100, ['status' => OrderStatus::Cancelled]);
        $this->order('2026-06-01 00:00:00', 100, ['status' => OrderStatus::Delivered, 'delivered_at' => '2026-06-11 00:00:00']);

        $p = $this->metrics()->pipeline();
        $counts = collect($p['stages'])->mapWithKeys(fn ($s) => [$s['status']->value => $s['count']]);

        $this->assertSame(1, $counts['pending']);
        $this->assertSame(2, $counts['in_production']);
        $this->assertSame(1, $counts['delivered']);
        $this->assertSame(4, $p['active']);
        $this->assertSame(1, $p['cancelled']);
        $this->assertSame(10.0, $p['days_to_deliver']);
    }

    public function test_fabrics_are_ranked_by_suits_ordered(): void
    {
        $this->fabrics();

        $a = $this->order('2026-06-01', 400);
        $a->items()->create(['fabric_id' => 1, 'fabric_name' => 'Navy Twill', 'fabric_image' => 'n.png', 'price' => 200, 'quantity' => 2, 'design' => [], 'summary' => '']);
        $b = $this->order('2026-06-02', 300);
        $b->items()->create(['fabric_id' => 2, 'fabric_name' => 'Grey Flannel', 'fabric_image' => 'g.png', 'price' => 300, 'quantity' => 1, 'design' => [], 'summary' => '']);

        $top = $this->metrics()->topFabrics();

        $this->assertSame(['Navy Twill', 'Grey Flannel'], $top->pluck('name')->all());
        $this->assertSame(2, $top[0]['suits']);
        $this->assertSame(400.0, $top[0]['revenue']);
        $this->assertEqualsWithDelta(66.7, $top[0]['share'], 0.1);
    }

    public function test_customers_are_placed_by_country_however_it_was_written(): void
    {
        $this->order('2026-06-01', 100, ['shipping' => ['country' => 'Bangladesh'], 'email' => 'a@x.test']);
        $this->order('2026-06-02', 100, ['shipping' => ['country' => 'bangladesh'], 'email' => 'A@x.test']);   // same buyer
        $this->order('2026-06-03', 250, ['shipping' => ['country' => 'UK'], 'email' => 'b@x.test']);
        $this->order('2026-06-04', 50, ['shipping' => ['country' => 'Atlantis']]);                         // not a place

        $countries = $this->metrics()->customersByCountry()->keyBy('code');

        $this->assertSame(['BD', 'GB'], $countries->keys()->all());
        $this->assertSame(2, $countries['BD']['orders']);
        $this->assertSame(1, $countries['BD']['customers'], 'the same email in a different case is one customer');
        $this->assertSame(250.0, $countries['GB']['revenue']);
        $this->assertSame([23.684994, 90.356331], $countries['BD']['coords']);
    }

    public function test_design_trends_read_what_customers_built(): void
    {
        $this->fabrics();

        $design = fn (string $body, string $lapel, string $lining = 'default') => [
            'body' => ['name' => $body],
            'lapel' => ['categoryName' => $lapel, 'subcategoryName' => 'Standard'],
            'sleeve' => ['name' => 'Default Shoulder'],
            'lining' => $lining === 'default' ? ['mode' => 'default'] : ['mode' => 'custom', 'name' => $lining],
        ];

        foreach ([['SB1', 'Notch'], ['SB1', 'Peak', 'Blue Silk'], ['DB2', 'Peak']] as $i => $d) {
            $o = $this->order('2026-06-0'.($i + 1), 100);
            $o->items()->create(['fabric_id' => 1, 'fabric_name' => 'Navy', 'price' => 100, 'quantity' => 1, 'design' => $design(...$d), 'summary' => '']);
        }

        $t = $this->metrics()->designTrends();

        $this->assertSame('SB1', $t['Body style']['choices'][0]['name']);
        $this->assertSame(2, $t['Body style']['choices'][0]['count']);
        $this->assertSame('Peak · Standard', $t['Lapel']['choices'][0]['name']);
        $this->assertSame('Standard lining', $t['Lining']['choices'][0]['name']);
        $this->assertSame('Blue Silk', $t['Lining']['choices'][1]['name']);
        $this->assertSame(3, $t['Body style']['orders']);
    }

    public function test_the_year_picker_runs_from_the_first_order_to_now(): void
    {
        $this->order('2024-03-01', 100);

        $this->assertSame([2026, 2025, 2024], $this->metrics()->salesYears());
    }
}
