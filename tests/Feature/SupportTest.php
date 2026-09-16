<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Mail\SampleRequestReceived;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Page;
use App\Models\SampleRequest;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_pages_render(): void
    {
        Faq::create(['category' => 'Fit', 'question' => 'How accurate is the estimate?', 'answer' => '<p>Within a centimetre or two.</p>']);

        $this->get('/contact')->assertOk()->assertInertia(fn ($p) => $p->component('Support/Contact'));
        $this->get('/samples')->assertOk()->assertInertia(fn ($p) => $p->component('Support/Samples')->where('max', 5));
        $this->get('/track')->assertOk()->assertInertia(fn ($p) => $p->component('Support/Track'));
        $this->get('/faqs')->assertOk()->assertInertia(fn ($p) => $p->component('Support/Faqs')->has('faqs', 1));
        $this->get('/p/about-us')->assertOk()->assertInertia(fn ($p) => $p->component('Support/Page')->where('page.title', 'About us'));
    }

    public function test_unpublished_pages_and_unknown_slugs_are_hidden(): void
    {
        Page::where('slug', 'terms')->update(['is_published' => false]);

        $this->get('/p/terms')->assertNotFound();
        $this->get('/p/does-not-exist')->assertNotFound();
    }

    public function test_contact_form_stores_the_message_and_notifies_the_atelier(): void
    {
        Mail::fake();
        SiteSetting::current()->update(['contact_email' => 'hello@example.test']);

        $this->post('/contact', ['name' => 'Alex', 'email' => 'alex@example.test', 'subject' => 'Fit', 'message' => 'Does the jacket run long?'])
            ->assertRedirect()
            ->assertSessionHas('sent', true);

        $this->assertSame('new', ContactMessage::first()->status);
        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('hello@example.test'));
    }

    public function test_sample_request_respects_the_limit_and_emails_both_sides(): void
    {
        Mail::fake();
        SiteSetting::current()->update(['samples_max' => 2, 'notify_email' => 'atelier@example.test']);
        $fabrics = collect(['Navy', 'Grey', 'Brown'])->map(fn ($n) => Fabric::create(['name' => $n, 'price' => 120, 'image' => 'https://x/s.png', 'is_default' => false, 'status' => true]));

        $shipping = ['name' => 'Alex Morgan', 'phone' => '', 'address' => '1 Road', 'address2' => '', 'city' => 'Dhaka', 'postcode' => '1212', 'country' => 'Bangladesh'];

        $this->post('/samples', ['email' => 'alex@example.test', 'fabric_ids' => $fabrics->pluck('id')->all(), 'shipping' => $shipping])
            ->assertSessionHasErrors('fabric_ids');

        $this->post('/samples', ['email' => 'Alex@Example.test', 'fabric_ids' => $fabrics->take(2)->pluck('id')->all(), 'shipping' => $shipping, 'notes' => 'A wedding suit'])
            ->assertRedirect()
            ->assertSessionHas('sent', true);

        $request = SampleRequest::first();
        $this->assertSame('alex@example.test', $request->email);
        $this->assertSame(['Navy', 'Grey'], collect($request->fabrics)->pluck('name')->all());
        Mail::assertSent(SampleRequestReceived::class, fn ($mail) => $mail->hasTo('alex@example.test') && $mail->forCustomer);
        Mail::assertSent(SampleRequestReceived::class, fn ($mail) => $mail->hasTo('atelier@example.test') && ! $mail->forCustomer);

        $request->update(['status' => 'sent']);
        $this->assertNotNull($request->fresh()->sent_at);
    }

    public function test_track_order_opens_the_receipt_only_for_a_matching_email(): void
    {
        $order = Order::create([
            'email' => 'buyer@example.test', 'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'pending',
            'subtotal' => 120, 'shipping_cost' => 0, 'total' => 120, 'currency' => 'USD',
            'shipping' => ['name' => 'B', 'phone' => '', 'address' => 'A', 'address2' => null, 'city' => 'C', 'postcode' => 'P', 'country' => 'X'],
            'body_profile' => ['name' => 'P', 'height_cm' => 180, 'weight_kg' => 80, 'age' => 30, 'measurements' => []],
        ]);

        $this->post('/track', ['number' => strtolower($order->number), 'email' => 'wrong@example.test'])->assertSessionHasErrors('number');
        $this->get(route('orders.show', $order))->assertNotFound();

        $this->post('/track', ['number' => strtolower($order->number), 'email' => 'Buyer@example.test'])->assertRedirect(route('orders.show', $order));
        $this->get(route('orders.show', $order))->assertOk();
    }
}
