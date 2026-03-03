<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_type' => 'monthly',
            'amount' => 999.00,
            'payment_method' => 'manual',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn () => [
            'plan_type' => 'yearly',
            'amount' => 9999.00,
            'expires_at' => now()->addDays(365),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(1),
            'status' => 'expired',
        ]);
    }

    public function expiringSoon(int $daysLeft = 3): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(27),
            'expires_at' => now()->addDays($daysLeft),
            'status' => 'active',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }
}
