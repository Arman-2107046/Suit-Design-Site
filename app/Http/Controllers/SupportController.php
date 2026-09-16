<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Mail\SampleRequestReceived;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Page;
use App\Models\SampleRequest;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function contact(): Response
    {
        return Inertia::render('Support/Contact', [
            'contact' => SiteSetting::current()->toContactArray(),
        ]);
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $message = ContactMessage::create($data);

        if ($to = SiteSetting::current()->notifyAddress()) {
            rescue(fn () => Mail::to($to)->send(new ContactMessageReceived($message)));
        }

        return back()->with('sent', true);
    }

    public function samples(Request $request): Response
    {
        $settings = SiteSetting::current();

        return Inertia::render('Support/Samples', [
            'intro' => $settings->samples_intro,
            'image' => $settings->samples_image_url,
            'max' => (int) $settings->samples_max,
            'fabrics' => Fabric::where('status', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'price', 'image']),
            'defaults' => $request->user() ? ['name' => $request->user()->name, 'email' => $request->user()->email] : null,
        ]);
    }

    public function requestSamples(Request $request): RedirectResponse
    {
        $max = (int) SiteSetting::current()->samples_max;

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'fabric_ids' => ['required', 'array', 'min:1', "max:{$max}"],
            'fabric_ids.*' => ['integer', Rule::exists('fabrics', 'id')->where('status', true)],
            'shipping' => ['required', 'array'],
            'shipping.name' => ['required', 'string', 'max:120'],
            'shipping.phone' => ['nullable', 'string', 'max:40'],
            'shipping.address' => ['required', 'string', 'max:255'],
            'shipping.address2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => ['required', 'string', 'max:120'],
            'shipping.postcode' => ['required', 'string', 'max:20'],
            'shipping.country' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fabrics = Fabric::whereIn('id', $data['fabric_ids'])->orderBy('sort_order')->get(['id', 'name', 'image']);

        $sample = SampleRequest::create([
            'user_id' => $request->user()?->id,
            'email' => strtolower($data['email']),
            'fabrics' => $fabrics->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'image' => $f->image])->all(),
            'shipping' => $data['shipping'],
            'notes' => $data['notes'] ?? null,
        ]);

        rescue(fn () => Mail::to($sample->email)->send(new SampleRequestReceived($sample, forCustomer: true)));
        if ($to = SiteSetting::current()->notifyAddress()) {
            rescue(fn () => Mail::to($to)->send(new SampleRequestReceived($sample)));
        }

        return back()->with('sent', true);
    }

    public function track(): Response
    {
        return Inertia::render('Support/Track', [
            'image' => SiteSetting::current()->track_image_url,
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::where('number', strtoupper(trim($data['number'])))
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        if (! $order) {
            return back()->withErrors(['number' => 'We could not find an order with that number and email.'])->withInput();
        }

        // The receipt checks this list, so a matched lookup opens it for the rest of the session.
        $request->session()->push('guest_orders', $order->id);

        return redirect()->route('orders.show', $order);
    }

    public function faqs(): Response
    {
        return Inertia::render('Support/Faqs', [
            'faqs' => Faq::where('is_published', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'category', 'question', 'answer']),
        ]);
    }

    public function page(Page $page): Response
    {
        abort_unless($page->is_published, 404);

        return Inertia::render('Support/Page', [
            'page' => $page->only(['slug', 'title', 'subtitle', 'hero_image_url', 'body', 'updated_at']),
        ]);
    }
}
