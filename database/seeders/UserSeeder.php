<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@platform.com',
            'password' => bcrypt('12345678'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
