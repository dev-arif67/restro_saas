<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@mail.com',
            'password' => '12345678',
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'active',
        ]);

        // Create Demo Restaurant
        $tenant = Tenant::create([
            'name' => 'Demo Restaurant',
            'slug' => 'demo-restaurant',
            'email' => 'demo@restaurant.com',
            'phone' => '+880171234567',
            'address' => '123 Food Street, Dhaka',
            'payment_mode' => 'platform',
            'commission_rate' => 5.00,
            'tax_rate' => 5.00,
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        // Restaurant Admin
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Restaurant Owner',
            'email' => 'owner@demo-restaurant.com',
            'password' => '12345678',
            'role' => User::ROLE_RESTAURANT_ADMIN,
            'status' => 'active',
        ]);

        // Staff
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Waiter Ali',
            'email' => 'waiter@demo-restaurant.com',
            'password' => '12345678',
            'role' => User::ROLE_STAFF,
            'status' => 'active',
        ]);

        // Kitchen
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Chef Karim',
            'email' => 'kitchen@demo-restaurant.com',
            'password' => '12345678',
            'role' => User::ROLE_KITCHEN,
            'status' => 'active',
        ]);

        // Subscription
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_type' => 'yearly',
            'amount' => 9999,
            'payment_method' => 'manual',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        // Tables
        for ($i = 1; $i <= 10; $i++) {
            RestaurantTable::create([
                'tenant_id' => $tenant->id,
                'table_number' => 'T' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'qr_code' => 'TBL-' . $tenant->id . '-T' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-' . Str::upper(Str::random(6)),
                'capacity' => $i <= 4 ? 2 : ($i <= 7 ? 4 : 6),
                'status' => 'available',
            ]);
        }

        // Categories & Menu Items
        $categories = [
            'Appetizers' => [
                ['name' => 'Spring Rolls', 'price' => 180, 'description' => 'Crispy vegetable spring rolls'],
                ['name' => 'Chicken Wings', 'price' => 320, 'description' => 'Spicy buffalo chicken wings'],
                ['name' => 'Soup of the Day', 'price' => 150, 'description' => 'Fresh daily soup'],
            ],
            'Main Course' => [
                ['name' => 'Grilled Chicken', 'price' => 550, 'description' => 'Herb grilled chicken with sides'],
                ['name' => 'Beef Steak', 'price' => 850, 'description' => 'Premium beef steak medium-rare'],
                ['name' => 'Fish & Chips', 'price' => 480, 'description' => 'Battered fish with french fries'],
                ['name' => 'Chicken Biryani', 'price' => 380, 'description' => 'Aromatic basmati rice with chicken'],
                ['name' => 'Mutton Curry', 'price' => 620, 'description' => 'Slow-cooked mutton in spices'],
            ],
            'Pizzas' => [
                ['name' => 'Margherita', 'price' => 450, 'description' => 'Classic mozzarella and basil'],
                ['name' => 'Pepperoni', 'price' => 520, 'description' => 'Pepperoni with mozzarella'],
                ['name' => 'BBQ Chicken', 'price' => 580, 'description' => 'BBQ sauce with grilled chicken'],
            ],
            'Beverages' => [
                ['name' => 'Fresh Lime', 'price' => 80, 'description' => 'Freshly squeezed lime juice'],
                ['name' => 'Mango Lassi', 'price' => 120, 'description' => 'Creamy mango yogurt drink'],
                ['name' => 'Iced Coffee', 'price' => 180, 'description' => 'Cold brew with ice and milk'],
                ['name' => 'Soft Drink', 'price' => 60, 'description' => 'Coca-Cola / Sprite / Fanta'],
            ],
            'Desserts' => [
                ['name' => 'Chocolate Brownie', 'price' => 220, 'description' => 'Warm brownie with ice cream'],
                ['name' => 'Cheesecake', 'price' => 280, 'description' => 'New York style cheesecake'],
                ['name' => 'Ice Cream', 'price' => 150, 'description' => 'Three scoops, choice of flavor'],
            ],
        ];

        $sortOrder = 0;
        foreach ($categories as $categoryName => $items) {
            $category = Category::create([
                'tenant_id' => $tenant->id,
                'name' => $categoryName,
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);

            $itemSort = 0;
            foreach ($items as $item) {
                MenuItem::create([
                    'tenant_id' => $tenant->id,
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'is_active' => true,
                    'sort_order' => $itemSort++,
                ]);
            }
        }

        // Vouchers
        Voucher::create([
            'tenant_id' => $tenant->id,
            'code' => 'WELCOME10',
            'discount_value' => 10,
            'type' => 'percentage',
            'min_purchase' => 500,
            'expiry_date' => now()->addMonths(3),
            'is_active' => true,
            'max_uses' => 100,
        ]);

        Voucher::create([
            'tenant_id' => $tenant->id,
            'code' => 'FLAT50',
            'discount_value' => 50,
            'type' => 'fixed',
            'min_purchase' => 300,
            'expiry_date' => now()->addMonth(),
            'is_active' => true,
            'max_uses' => 50,
        ]);
    }
}
