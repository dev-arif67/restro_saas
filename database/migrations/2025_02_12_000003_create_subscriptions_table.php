<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('plan_type', ['monthly', 'yearly', 'custom'])->default('monthly');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable(); // bkash, sslcommerz, manual
            $table->string('payment_ref')->nullable();
            $table->string('transaction_id')->nullable();
            $table->date('starts_at');
            $table->date('expires_at');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
