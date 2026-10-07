<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\BodyProfile;
use App\Models\Fabric;
use App\Models\Order;
use App\Services\AdminNotifier;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeGateway;
use App\Support\BodyEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class CheckoutController extends Controller
{
    public const PAYMENT_METHODS = ['cash_on_delivery', 'bank_transfer'];

    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly OrderPayments $payments,
    ) {}

    /** Card first when Stripe is set up; otherwise the offline methods alone. */
    public function paymentMethods(): array
    {
        return $this->stripe->enabled() ? [OrderPayments::CARD, ...self::PAYMENT_METHODS] : self::PAYMENT_METHODS;
    }

    public function cart(): Response
    {
        return Inertia::render('Cart');
    }

    public function bodyProfile(Request $request): Response
    {
        return Inertia::render('Checkout/BodyProfile', [
            'fields' => BodyEstimator::FIELDS,
            'limits' => BodyEstimator::LIMITS,
            'profiles' => $this->savedProfiles($request),
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'height' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['height'])],
            'weight' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['weight'])],
            'age' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['age'])],
        ]);

        return response()->json(['measurements' => BodyEstimator::estimate($data['height'], $data['weight'], $data['age'])]);
    }

    public function storeBodyProfile(Request $request): RedirectResponse
    {
        $data = $this->validateProfile($request);

        $profile = $request->user()->bodyProfiles()->create($data);

        return back()->with('profile', $profile->toSnapshot());
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Checkout/Index', [
            'profiles' => $this->savedProfiles($request),
            'paymentMethods' => $this->paymentMethods(),
            'fields' => BodyEstimator::FIELDS,
        ]);
    }

    public function store(Request $request): HttpResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.fabric_id' => ['required', 'integer', Rule::exists('fabrics', 'id')->where('status', true)],
            'items.*.quantity' => ['required', 'integer', 'between:1,5'],
            'items.*.design' => ['required', 'array'],
            'items.*.summary' => ['required', 'array'],
            'items.*.summary.*.label' => ['required', 'string', 'max:60'],
            'items.*.summary.*.value' => ['required', 'string', 'max:120'],
            'body_profile' => ['required', 'array'],
            'body_profile.name' => ['required', 'string', 'max:80'],
            'body_profile.height_cm' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['height'])],
            'body_profile.weight_kg' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['weight'])],
            'body_profile.age' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['age'])],
            'body_profile.measurements' => ['required', 'array'],
            ...collect(BodyEstimator::FIELDS)->mapWithKeys(fn ($label, $key) => ["body_profile.measurements.{$key}" => ['required', 'numeric', 'between:10,250']])->all(),
            'shipping' => ['required', 'array'],
            'shipping.name' => ['required', 'string', 'max:120'],
            'shipping.phone' => ['required', 'string', 'max:40'],
            'shipping.address' => ['required', 'string', 'max:255'],
            'shipping.address2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => ['required', 'string', 'max:120'],
            'shipping.postcode' => ['required', 'string', 'max:20'],
            'shipping.country' => ['required', 'string', 'max:80'],
            'payment_method' => ['required', Rule::in($this->paymentMethods())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fabrics = Fabric::whereIn('id', collect($data['items'])->pluck('fabric_id'))->get()->keyBy('id');

        $order = DB::transaction(function () use ($request, $data, $fabrics) {
            $subtotal = collect($data['items'])->sum(fn ($item) => (float) $fabrics[$item['fabric_id']]->price * $item['quantity']);

            $order = Order::create([
                'user_id' => $request->user()?->id,
                'email' => strtolower($data['email']),
                'status' => OrderStatus::Pending,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'total' => $subtotal,
                'currency' => 'USD',
                'shipping' => $data['shipping'],
                'body_profile' => $data['body_profile'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $fabric = $fabrics[$item['fabric_id']];
                $order->items()->create([
                    'fabric_id' => $fabric->id,
                    'fabric_name' => $fabric->name,
                    'fabric_image' => $fabric->image,
                    'price' => $fabric->price,
                    'quantity' => $item['quantity'],
                    'design' => $item['design'],
                    'summary' => $item['summary'],
                ]);
            }

            return $order;
        });

        // Guests can open the receipt they just placed from this browser session.
        if (! $request->user()) {
            $request->session()->push('guest_orders', $order->id);
        }

        /* Card orders are confirmed by email once Stripe says they are paid, not before */
        if ($order->payment_method === OrderPayments::CARD) {
            return $this->startCardPayment($order);
        }

        $this->payments->sendConfirmation($order);
        AdminNotifier::orderPlaced($order);

        return redirect()->route('orders.show', $order)->with('placed', true);
    }

    /**
     * Send the customer to Stripe's hosted page to pay for an order. Used at
     * checkout and again from the order page if the first attempt did not go
     * through. If Stripe cannot be reached, the order is kept and the order
     * page offers to try again.
     */
    public function startCardPayment(Order $order): HttpResponse
    {
        try {
            $session = $this->stripe->createCheckoutSession(
                $order->loadMissing('items'),
                route('checkout.stripe.success', $order).'?session_id={CHECKOUT_SESSION_ID}',
                route('checkout.stripe.cancel', $order),
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('orders.show', $order)->with('payment', 'unavailable');
        }

        $order->forceFill(['stripe_session_id' => $session['id'], 'payment_status' => 'pending'])->save();

        /* A full-page visit, since Stripe's page is not part of this app */
        return Inertia::location($session['url']);
    }

    /** @return array<int, array<string, mixed>> */
    private function savedProfiles(Request $request): array
    {
        return $request->user()
            ? $request->user()->bodyProfiles()->latest()->get()->map(fn (BodyProfile $p) => $p->toSnapshot())->all()
            : [];
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'height_cm' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['height'])],
            'weight_kg' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['weight'])],
            'age' => ['required', 'integer', 'between:' . implode(',', BodyEstimator::LIMITS['age'])],
            'measurements' => ['required', 'array'],
            ...collect(BodyEstimator::FIELDS)->mapWithKeys(fn ($label, $key) => ["measurements.{$key}" => ['required', 'numeric', 'between:10,250']])->all(),
        ]);
    }
}
