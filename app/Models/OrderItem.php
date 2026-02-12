<?php

namespace App\Models;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'qty',
        'price_at_sale',
        'line_total',
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price_at_sale' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    // Auto-calculate line total
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item) {
            $item->line_total = $item->qty * $item->price_at_sale;
        });

        static::updating(function (OrderItem $item) {
            $item->line_total = $item->qty * $item->price_at_sale;
        });
    }
}
