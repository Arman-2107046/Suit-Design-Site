<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'number', 'user_id', 'email', 'status', 'payment_method', 'payment_status',
        'subtotal', 'shipping_cost', 'total', 'currency', 'shipping', 'body_profile', 'notes',
        'paid_at', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'shipping' => 'array',
        'body_profile' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->number ??= static::nextNumber();
        });

        static::updating(function (Order $order) {
            if ($order->isDirty('status')) {
                if ($order->status === OrderStatus::Shipped) {
                    $order->shipped_at ??= now();
                }
                if ($order->status === OrderStatus::Delivered) {
                    $order->delivered_at ??= now();
                }
            }
        });
    }

    /* CT-2026-A7K3QZ: readable on a receipt, not guessable from the previous one. */
    public static function nextNumber(): string
    {
        do {
            $number = 'CT-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (static::where('number', $number)->exists());

        return $number;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** What the storefront renders for an order. */
    public function toCustomerArray(): array
    {
        return [
            'number' => $this->number,
            'email' => $this->email,
            'status' => $this->status->toArray(),
            'journey' => array_map(fn (OrderStatus $s) => $s->toArray(), OrderStatus::journey()),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'subtotal' => (float) $this->subtotal,
            'shipping_cost' => (float) $this->shipping_cost,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'shipping' => $this->shipping,
            'body_profile' => $this->body_profile,
            'notes' => $this->notes,
            'placed_at' => $this->created_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'fabric_id' => $item->fabric_id,
                'fabric_name' => $item->fabric_name,
                'fabric_image' => $item->fabric_image,
                'price' => (float) $item->price,
                'quantity' => $item->quantity,
                'summary' => $item->summary,
            ])->all(),
        ];
    }
}
