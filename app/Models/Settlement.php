<?php

namespace App\Models;

use App\Models\SettlementPayment;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'total_sold',
        'commission_rate',
        'commission_amount',
        'total_paid',
        'payable_balance',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_sold' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'payable_balance' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    // Relationships
    public function payments()
    {
        return $this->hasMany(SettlementPayment::class);
    }

    // Helpers
    public function recalculate(): void
    {
        $this->total_paid = $this->payments->sum('amount');
        $this->payable_balance = ($this->total_sold - $this->commission_amount) - $this->total_paid;

        if ($this->payable_balance <= 0) {
            $this->status = 'settled';
        } elseif ($this->total_paid > 0) {
            $this->status = 'partial';
        }

        $this->save();
    }
}
