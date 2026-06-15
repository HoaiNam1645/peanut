<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ref_id',
        'seller_id',
        'seller_ref',
        'store_id',
        'shipping_label',
        'shipping_service',
        'shipping_method',
        'shipping_json',
        'shipping_cost',
        'tracking_id',
        'tracking_link',
        'fulfill_status',
        'processing_status',
        'payment_status',
        'total_cost',
        'paid_cost',
        'print_cost',
        'extra_fee',
        'refund_fee',
        'embroidery_fee',
        'priority_fee',  // Fee for priority fulfillment
        'convert_label',
        'override_label',
        'note',
        'order_stt',
        'order_type',
        'product_type',
        'process_time',
        'complete_time',
        'shipped_at',
        'scan_early',
        'merged_url',
        'post_json',
        'first_name',
        'last_name',
        'phone',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'fulfillment_priority',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'paid_cost' => 'decimal:2',
        'print_cost' => 'decimal:2',
        'extra_fee' => 'decimal:2',
        'refund_fee' => 'decimal:2',
        'embroidery_fee' => 'decimal:2',
        'priority_fee' => 'decimal:2',
        'process_time' => 'integer',
        'complete_time' => 'datetime',
        'shipped_at' => 'datetime',
        'scan_early' => 'boolean',
    ];

    /**
     * Stamp shipped_at at the exact moment an order transitions INTO the shipped
     * state, regardless of which code path triggered it (manual change, batch
     * change, or the auto-transition when the last shipout label is scanned).
     * Centralising this here keeps the timestamp accurate across every path.
     */
    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            if (!$order->isDirty('fulfill_status')) {
                return;
            }

            $newStatus = $order->fulfill_status;
            $oldStatus = $order->getOriginal('fulfill_status');

            if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {
                // Entering shipped → record when the label scan completed.
                $order->shipped_at = now();
            } elseif ($oldStatus === 'shipped' && $newStatus !== 'shipped') {
                // Reverted out of shipped (e.g. admin correction) → clear it so a
                // later re-ship captures a fresh, accurate timestamp.
                $order->shipped_at = null;
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function metas(): HasMany
    {
        return $this->hasMany(OrderMeta::class, 'object_id');
    }

    public function tracking(): HasOne
    {
        return $this->hasOne(Tracking::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Support::class);
    }

    /**
     * Calculate priority fee based on fulfillment_priority and seller's tier
     * 
     * @param int $tierId User's tier ID (0=Silver, 1=Gold, 2=Platinum, 3=Diamond)
     * @return float Priority fee amount
     */
    public static function calculatePriorityFee(string $priorityName, int $tierId): float
    {
        return FulfillmentPriority::getPriceForTier($priorityName, $tierId);
    }

    /**
     * Get priority fee for this order based on seller's tier
     */
    public function getPriorityFeeForSeller(): float
    {
        if (!$this->seller || $this->fulfillment_priority === 'normal') {
            return 0;
        }

        $tierId = $this->seller->profile->private_seller ?? 0;
        return self::calculatePriorityFee($this->fulfillment_priority, $tierId);
    }
}
