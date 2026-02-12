<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('grand_total', 10, 2)->default(0.00);
            $table->enum('type', ['dine', 'parcel'])->default('dine');
            $table->enum('status', [
                'placed',
                'confirmed',
                'preparing',
                'ready',
                'served',
                'completed',
                'cancelled'
            ])->default('placed');
            $table->string('ip_address', 45)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'table_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
