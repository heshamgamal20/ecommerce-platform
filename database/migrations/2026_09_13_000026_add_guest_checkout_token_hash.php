<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->string('guest_checkout_token_hash', 64)->nullable()->after('guest_phone');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->dropColumn('guest_checkout_token_hash');
        });
    }
};
