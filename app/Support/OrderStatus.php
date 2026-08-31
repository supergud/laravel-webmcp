<?php

declare(strict_types=1);

namespace App\Support;

enum OrderStatus: string
{
    /** Prepared by checkout, waiting for a person to confirm it. */
    case Draft = 'draft';

    /** Confirmed by a person. This demo treats confirmation as payment. */
    case Paid = 'paid';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('shop.status.'.$this->value);
    }

    public function isPlaced(): bool
    {
        return $this === self::Paid;
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Paid => 'green',
            self::Cancelled => 'zinc',
        };
    }
}
