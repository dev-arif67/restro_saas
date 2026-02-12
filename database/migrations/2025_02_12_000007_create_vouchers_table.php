<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code')->index();
            $table->decimal('discount_value', 10, 2);
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('min_purchase', 10, 2)->default(0.00);
            $table->date('expiry_date');
            $table->boolean('is_active')->default(true);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
