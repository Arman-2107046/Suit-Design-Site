<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Mail\OrderPlaced;
use App\Models\BodyProfile;
use App\Models\Fabric;
use App\Models\Order;
use App\Support\BodyEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public const PAYMENT_METHODS = ['cash_on_delivery', 'bank_transfer'];

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
            'paymentMethods' => self::PAYMENT_METHODS,
            'fields' => BodyEstimator::FIELDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
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

        rescue(fn () => Mail::to($order->email)->send(new OrderPlaced($order)));

        // Guests can open the receipt they just placed from this browser session.
        if (! $request->user()) {
            $request->session()->push('guest_orders', $order->id);
        }

        return redirect()->route('orders.show', $order)->with('placed', true);
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
