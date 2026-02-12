<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('table_number');
            $table->string('qr_code')->nullable();
            $table->enum('status', ['available', 'occupied', 'reserved', 'inactive'])->default('available');
            $table->integer('capacity')->default(4);
            $table->timestamps();

            $table->unique(['tenant_id', 'table_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
