<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_order_items', function (Blueprint $table): void {
            $table->text('purchase_price')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_items', fn (Blueprint $table) => $table->dropColumn('purchase_price'));
    }
};
