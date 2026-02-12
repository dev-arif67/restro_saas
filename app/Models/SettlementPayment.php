<?php

namespace App\Models;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettlementPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'amount',
        'payment_method',
        'payment_ref',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }
}
