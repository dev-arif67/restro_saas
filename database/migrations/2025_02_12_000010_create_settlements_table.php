<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('total_sold', 12, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->decimal('commission_amount', 12, 2)->default(0.00);
            $table->decimal('total_paid', 12, 2)->default(0.00);
            $table->decimal('payable_balance', 12, 2)->default(0.00);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['pending', 'partial', 'settled'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_ref')->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_payments');
        Schema::dropIfExists('settlements');
    }
};
