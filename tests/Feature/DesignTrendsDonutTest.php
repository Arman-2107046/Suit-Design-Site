<?php

namespace Tests\Feature;

use App\Filament\Widgets\DesignTrends;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DesignTrendsDonutTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    private function suit(string $body): void
    {
        $fabric = Fabric::firstOrCreate(['name' => 'Navy'], ['price' => 200, 'image' => 'f.png', 'is_default' => true, 'status' => true]);

        $order = Order::create([
            'number' => 'CT-'.(++$this->n), 'email' => "b{$this->n}@x.test", 'status' => 'confirmed',
            'payment_method' => 'card', 'payment_status' => 'paid', 'subtotal' => 200, 'shipping_cost' => 0,
            'total' => 200, 'currency' => 'USD', 'shipping' => ['country' => 'Bangladesh'], 'body_profile' => [],
        ]);

        $order->items()->create([
            'fabric_id' => $fabric->id, 'fabric_name' => 'Navy', 'price' => 200, 'quantity' => 1, 'summary' => '',
            'design' => ['body' => ['name' => $body], 'lining' => ['mode' => 'default']],
        ]);
    }

    /** The body-style donut, as the view receives it. */
    private function bodyDonut(): array
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        return Livewire::test(DesignTrends::class)->viewData('parts')['Body style'];
    }

    public function test_the_top_four_get_slices_and_the_rest_become_other(): void
    {
        foreach (['SB1', 'SB1', 'SB1', 'SB2', 'SB2', 'DB2', 'DB4'] as $body) {
            $this->suit($body);
        }
        $this->suit('SB3');   // eighth suit, fifth style: falls outside the top four

        $donut = $this->bodyDonut();
        $names = array_column($donut['slices'], 'name');

        $this->assertSame(['SB1', 'SB2', 'DB2', 'DB4', 'Other'], $names);
        $this->assertEqualsWithDelta(37.5, $donut['slices'][0]['share'], 0.01);
        $this->assertEqualsWithDelta(12.5, $donut['slices'][4]['share'], 0.01, 'SB3 is the whole of Other');
        $this->assertSame('SB1', $donut['lead']['name']);
    }

    public function test_the_slices_always_add_up_to_the_whole(): void
    {
        foreach (['SB1', 'SB2', 'SB2', 'DB2', 'DB4', 'SB3', 'SB3'] as $body) {
            $this->suit($body);
        }

        $total = array_sum(array_column($this->bodyDonut()['slices'], 'share'));

        $this->assertEqualsWithDelta(100.0, $total, 0.01);
    }

    public function test_the_gradient_runs_round_the_circle_in_order_with_hairline_gaps(): void
    {
        foreach (['SB1', 'SB1', 'SB2'] as $body) {
            $this->suit($body);
        }

        $gradient = $this->bodyDonut()['gradient'];

        /* SB1 two thirds, SB2 one third, a 0.6% gap before each boundary */
        $this->assertSame(
            'conic-gradient(#4f46e5 0.00% 66.07%, transparent 66.07% 66.67%, #06b6d4 66.67% 99.40%, transparent 99.40% 100.00%)',
            $gradient
        );
    }

    public function test_a_single_choice_is_a_full_ring_with_no_gap(): void
    {
        $this->suit('SB1');

        $donut = $this->bodyDonut();

        $this->assertSame('conic-gradient(#4f46e5 0.00% 100.00%)', $donut['gradient']);
        $this->assertCount(1, $donut['slices']);
    }
}
