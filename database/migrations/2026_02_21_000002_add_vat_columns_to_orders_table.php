<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 50)->nullable()->after('order_number');
            $table->decimal('net_amount', 12, 2)->default(0)->after('discount');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('net_amount');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');

            // Update subtotal, discount, grand_total to decimal(12,2) for larger amounts
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('discount', 12, 2)->default(0)->change();
            $table->decimal('grand_total', 12, 2)->default(0)->change();

            // Unique invoice number per restaurant (composite)
            $table->unique(['tenant_id', 'invoice_number'], 'orders_tenant_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_tenant_invoice_unique');
            $table->dropColumn(['invoice_number', 'net_amount', 'vat_rate', 'vat_amount']);

            $table->decimal('subtotal', 10, 2)->default(0)->change();
            $table->decimal('discount', 10, 2)->default(0)->change();
            $table->decimal('grand_total', 10, 2)->default(0)->change();
        });
    }
};
