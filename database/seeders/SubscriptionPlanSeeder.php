<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Monthly',
                'slug' => 'monthly',
                'price' => 999.00,
                'duration_days' => 30,
                'features' => [
                    'Unlimited orders',
                    'QR code ordering',
                    'Kitchen display',
                    'POS system',
                    'Basic reports',
                    'Email support',
                ],
                'max_users' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Yearly',
                'slug' => 'yearly',
                'price' => 9999.00,
                'duration_days' => 365,
                'features' => [
                    'Unlimited orders',
                    'QR code ordering',
                    'Kitchen display',
                    'POS system',
                    'Advanced reports',
                    'VAT reports',
                    'Priority support',
                    '2 months free',
                ],
                'max_users' => 10,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 24999.00,
                'duration_days' => 365,
                'features' => [
                    'Unlimited orders',
                    'QR code ordering',
                    'Kitchen display',
                    'POS system',
                    'Advanced reports',
                    'VAT reports',
                    'API access',
                    'Custom branding',
                    'Dedicated support',
                    'Unlimited users',
                ],
                'max_users' => 999,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
