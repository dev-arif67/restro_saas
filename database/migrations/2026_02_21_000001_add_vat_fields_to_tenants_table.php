<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('vat_registered')->default(false)->after('tax_rate');
            $table->string('vat_number', 50)->nullable()->after('vat_registered');
            $table->decimal('default_vat_rate', 5, 2)->default(5.00)->after('vat_number');
            $table->boolean('vat_inclusive')->default(false)->after('default_vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['vat_registered', 'vat_number', 'default_vat_rate', 'vat_inclusive']);
        });
    }
};
