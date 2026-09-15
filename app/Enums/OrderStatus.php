<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case QualityCheck = 'quality_check';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::InProduction => 'In production',
            self::QualityCheck => 'Quality check',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'info',
            self::InProduction, self::QualityCheck => 'warning',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /* What the customer sees under each step of the timeline. */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'We have received your order and are reviewing your measurements.',
            self::Confirmed => 'Your order is confirmed and your cloth has been reserved.',
            self::InProduction => 'Our tailors are cutting and sewing your suit.',
            self::QualityCheck => 'Your suit is being inspected, pressed and finished.',
            self::Shipped => 'Your suit is on its way.',
            self::Delivered => 'Your suit has been delivered. Enjoy it.',
            self::Cancelled => 'This order was cancelled.',
        };
    }

    /** The customer-facing journey, in order (cancelled sits outside it). */
    public static function journey(): array
    {
        return [self::Pending, self::Confirmed, self::InProduction, self::QualityCheck, self::Shipped, self::Delivered];
    }

    public function step(): int
    {
        $index = array_search($this, self::journey(), true);

        return $index === false ? -1 : $index;
    }

    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->getLabel(), 'description' => $this->description(), 'step' => $this->step()];
    }
}
