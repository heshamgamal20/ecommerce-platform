<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('purchase_price')->nullable()->after('status');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->text('purchase_price')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('purchase_price'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('purchase_price'));
    }
};
