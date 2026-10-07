<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\AdminSearchProvider;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\RankYakSetting;
use App\Models\SampleRequest;
use App\Models\User;
use App\Services\AdminNotifier;
use App\Services\Payments\OrderPayments;
use App\Services\RankYak\RankYakImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminBellAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private Admin $sara;

    private Admin $arman;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->sara = Admin::factory()->create(['name' => 'Sara']);
        $this->arman = Admin::factory()->superAdmin()->create(['name' => 'Arman']);
    }

    private function order(array $overrides = []): Order
    {
        $fabric = Fabric::firstOrCreate(['name' => 'Navy Twill'], ['price' => 249, 'image' => 'https://x/n.png', 'is_default' => true, 'status' => true]);

        $order = Order::create($overrides + [
            'email' => 'alex@example.test', 'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'pending',
            'subtotal' => 498, 'shipping_cost' => 0, 'total' => 498, 'currency' => 'USD',
            'shipping' => ['name' => 'Alex Morgan', 'phone' => '+880 1711 223344', 'city' => 'Dhaka', 'country' => 'Bangladesh'],
            'body_profile' => [],
        ]);
        $order->items()->create(['fabric_id' => $fabric->id, 'fabric_name' => 'Navy Twill', 'price' => 249, 'quantity' => 2, 'design' => [], 'summary' => []]);

        return $order;
    }

    /** @return list<string> the titles in an admin's bell */
    private function bell(Admin $admin): array
    {
        return $admin->fresh()->notifications()->latest()->get()->pluck('data.title')->all();
    }

    public function test_every_admin_hears_about_a_new_order(): void
    {
        $order = $this->order();

        AdminNotifier::orderPlaced($order);

        $this->assertSame(["New order {$order->number}"], $this->bell($this->sara));
        $this->assertSame(["New order {$order->number}"], $this->bell($this->arman));

        $data = $this->sara->fresh()->notifications()->first()->data;
        $this->assertStringContainsString('Alex Morgan · $498.00 · 2 suits', $data['body']);
        $this->assertSame(OrderResource::getUrl('view', ['record' => $order]), $data['actions'][0]['url']);
    }

    public function test_a_card_order_reaches_the_bell_once_paid_and_a_failed_payment_says_so(): void
    {
        $paid = $this->order(['payment_method' => 'card']);
        $this->assertSame([], $this->bell($this->sara), 'not while the customer is still on Stripe');

        app(OrderPayments::class)->markPaid($paid, 'pi_1');
        app(OrderPayments::class)->markPaid($paid, 'pi_1');   // Stripe reporting it twice
        $this->assertSame(["New order {$paid->number}, paid by card"], $this->bell($this->sara));

        $failed = $this->order(['payment_method' => 'card']);
        app(OrderPayments::class)->markFailed($failed);
        $this->assertContains("Card payment didn't go through: {$failed->number}", $this->bell($this->sara));
    }

    public function test_the_shop_forms_ring_the_bell(): void
    {
        $this->post('/contact', ['name' => 'Nadia', 'email' => 'nadia@example.test', 'subject' => 'Lapel width', 'message' => 'Which width suits a slim build?'])->assertRedirect();
        $this->assertContains('New message from Nadia', $this->bell($this->sara));

        $fabric = Fabric::firstOrCreate(['name' => 'Navy Twill'], ['price' => 249, 'image' => 'https://x/n.png', 'is_default' => true, 'status' => true]);
        $this->post('/samples', [
            'email' => 'tom@example.test', 'fabric_ids' => [$fabric->id],
            'shipping' => ['name' => 'Tom Reid', 'address' => '1 Road', 'city' => 'Chittagong', 'postcode' => '4000', 'country' => 'Bangladesh'],
        ])->assertRedirect();
        $this->assertContains('Swatches requested by Tom Reid', $this->bell($this->sara));
    }

    public function test_a_new_rankyak_article_rings_the_bell_but_a_refresh_does_not(): void
    {
        $settings = RankYakSetting::current();
        $importer = new RankYakImporter($settings, app(\App\Services\Cloudflare\CloudflareImages::class));
        $article = ['id' => '9', 'title' => 'Choosing a Wedding Suit', 'html' => '<p>Start with the season.</p>', 'published_at' => now()->subHour()->toIso8601String()];

        $importer->import($article);
        $importer->import(['title' => 'Choosing the Perfect Wedding Suit'] + $article);

        $this->assertSame(['RankYak article: Choosing a Wedding Suit'], $this->bell($this->sara));
    }

    public function test_search_finds_an_order_by_number_or_customer_whatever_the_case(): void
    {
        $order = $this->order();
        $this->order(['email' => 'someone@else.test', 'shipping' => ['name' => 'Rafi Ahmed']]);
        $this->actingAs($this->sara, 'admin');

        $titles = fn (string $q) => OrderResource::getGlobalSearchResults($q)->pluck('title')->all();

        $this->assertSame([$order->number], $titles(strtolower(substr($order->number, 0, 12))));
        $this->assertSame([$order->number], $titles('alex mor'));
        $this->assertSame([$order->number], $titles('ALEX@EXAMPLE'));
        $this->assertSame([$order->number], $titles('1711 223'));

        $result = OrderResource::getGlobalSearchResults('alex')->first();
        $this->assertSame(OrderResource::getUrl('view', ['record' => $order]), $result->url);
        $this->assertSame('Alex Morgan', $result->details['Customer']);
    }

    public function test_search_covers_customers_messages_and_swatch_requests(): void
    {
        $customer = User::factory()->create(['name' => 'Alexandra Khan', 'email' => 'alexandra@example.test']);
        ContactMessage::create(['name' => 'Alex Wu', 'email' => 'wu@example.test', 'subject' => 'Sizing', 'message' => 'Hi']);
        SampleRequest::create(['email' => 'pat@example.test', 'fabrics' => [['id' => 1, 'name' => 'Navy']], 'shipping' => ['name' => 'Alex Pat']]);
        $this->order();
        $this->actingAs($this->arman, 'admin');

        $categories = (new AdminSearchProvider)->getResults('alex')->getCategories();

        $this->assertSame(['orders', 'customers', 'contact messages', 'sample requests'], $categories->keys()->map(fn ($k) => strtolower($k))->all());

        $customerResult = $categories['customers'][0];
        $this->assertSame('Alexandra Khan', $customerResult->title);
        $this->assertSame(OrderResource::getUrl('index', ['search' => $customer->email]), $customerResult->url);
    }

    public function test_admins_only_find_what_they_are_allowed_to_open(): void
    {
        $this->actingAs($this->sara, 'admin');   // an admin, not a super admin

        $categories = (new AdminSearchProvider)->getResults('arman')->getCategories();

        $this->assertArrayNotHasKey('administrators', $categories->all(), 'the team list is for super admins');
    }
}
