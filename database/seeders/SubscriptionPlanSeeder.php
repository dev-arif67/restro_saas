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
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 999.00,
                'annual_price' => 9990.00,
                'trial_days' => 7,
                'duration_days' => 30,
                'features' => [
                    'Up to 1,500 orders per month',
                    'Up to 20 tables',
                    'Up to 5 staff users',
                    'Up to 100 menu items',
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
                'name' => 'Professional',
                'slug' => 'professional',
                'price' => 2499.00,
                'annual_price' => 24990.00,
                'trial_days' => 14,
                'duration_days' => 30,
                'features' => [
                    'Up to 10,000 orders per month',
                    'Up to 60 tables',
                    'Up to 15 staff users',
                    'Up to 500 menu items',
                    'QR code ordering',
                    'Kitchen display',
                    'POS system',
                    'Advanced reports',
                    'VAT reports',
                    'Priority support',
                ],
                'max_users' => 15,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 5999.00,
                'annual_price' => 59990.00,
                'trial_days' => 14,
                'duration_days' => 30,
                'features' => [
                    'Unlimited orders',
                    'Unlimited tables',
                    'Unlimited menu items',
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
