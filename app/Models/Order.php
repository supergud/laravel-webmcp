<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $number
 * @property OrderStatus $status
 * @property int $total
 * @property string $currency
 * @property string $shipping_name
 * @property string $shipping_email
 * @property string $shipping_address
 * @property string|null $confirmation_token
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'number', 'status', 'total', 'currency',
        'shipping_name', 'shipping_email', 'shipping_address',
        'confirmation_token', 'expires_at', 'confirmed_at',
    ];

    /**
     * Never serialise the confirmation token. It is the one secret on this
     * model and it must not travel to a tool response, an API payload or a
     * log line.
     *
     * @var list<string>
     */
    protected $hidden = ['confirmation_token'];

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders that were actually placed, as opposed to unconfirmed drafts.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePlaced(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeOwnedBy(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function isDraft(): bool
    {
        return $this->status === OrderStatus::Draft;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConfirmable(): bool
    {
        return $this->isDraft() && ! $this->hasExpired();
    }

    /**
     * The shape returned to WebMCP tools and the JSON API.
     *
     * @return array<string, mixed>
     */
    public function toToolArray(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'currency' => $this->currency,
            'total' => $this->total,
            'placed_at' => $this->confirmed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'shipping_name' => $this->shipping_name,
            'items' => $this->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'name' => (string) $item->name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
            ])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
