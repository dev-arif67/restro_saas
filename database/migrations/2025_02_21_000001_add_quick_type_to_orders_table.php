<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine','parcel','quick') NOT NULL DEFAULT 'dine'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine','parcel') NOT NULL DEFAULT 'dine'");
    }
};
