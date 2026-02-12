<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'online'])->default('cash')->after('notes');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('payment_method');
            $table->string('transaction_id')->nullable()->after('payment_status');
            $table->string('payment_gateway')->nullable()->after('transaction_id'); // sslcommerz, bkash, shurjopay
            $table->timestamp('paid_at')->nullable()->after('payment_gateway');

            $table->index(['tenant_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'payment_status']);
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'transaction_id',
                'payment_gateway',
                'paid_at',
            ]);
        });
    }
};
