<?php

namespace App\Models;

use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Traits\BelongsToTenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'table_id',
        'voucher_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'type',
        'status',
        'ip_address',
        'notes',
        'payment_method',
        'payment_status',
        'transaction_id',
        'payment_gateway',
        'paid_at',
        'source',
        'served_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public const STATUS_FLOW = [
        'placed' => 'confirmed',
        'confirmed' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'served',
        'served' => 'completed',
    ];

    // Relationships
    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function servedBy()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeDineIn($query)
    {
        return $query->where('type', 'dine');
    }

    public function scopeParcel($query)
    {
        return $query->where('type', 'parcel');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helpers
    public static function generateOrderNumber(int $tenantId): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $count = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $count);
    }

    public function canAdvanceStatus(): bool
    {
        return isset(self::STATUS_FLOW[$this->status]);
    }

    public function getNextStatus(): ?string
    {
        return self::STATUS_FLOW[$this->status] ?? null;
    }

    public function advanceStatus(): bool
    {
        $next = $this->getNextStatus();
        if (!$next) return false;

        $this->update(['status' => $next]);
        return true;
    }

    public function isDineIn(): bool
    {
        return $this->type === 'dine';
    }

    public function isParcel(): bool
    {
        return $this->type === 'parcel';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum('line_total');
        $discount = 0;

        if ($this->voucher_id && $this->voucher) {
            $discount = $this->voucher->calculateDiscount($subtotal);
        }

        $afterDiscount = $subtotal - $discount;
        $tenant = $this->tenant;
        $tax = round($afterDiscount * ($tenant->tax_rate / 100), 2);
        $grandTotal = $afterDiscount + $tax;

        $this->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'grand_total' => $grandTotal,
        ]);
    }
}
