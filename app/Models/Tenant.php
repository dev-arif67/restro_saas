<?php

namespace App\Models;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Settlement;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'logo',
        'logo_dark',
        'favicon',
        'primary_color',
        'secondary_color',
        'accent_color',
        'description',
        'social_links',
        'banner_image',
        'authorized_wifi_ip',
        'payment_mode',
        'commission_rate',
        'currency',
        'tax_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'social_links' => 'array',
        ];
    }

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function tables()
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->latest();
    }

    // Helpers
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function isSubscriptionExpired(): bool
    {
        return !$this->hasActiveSubscription();
    }

    public function isWifiEnforced(): bool
    {
        return !empty($this->authorized_wifi_ip);
    }

    public function isPlatformCollection(): bool
    {
        return $this->payment_mode === 'platform';
    }
}
