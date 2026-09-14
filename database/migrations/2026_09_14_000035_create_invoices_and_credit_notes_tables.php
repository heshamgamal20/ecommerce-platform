<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('customer_orders')->cascadeOnDelete();
            $table->string('number', 60)->unique();
            $table->string('status', 20)->default('issued')->index();
            $table->string('type', 20)->default('tax');
            $table->string('currency', 3)->default('EGP');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('customer_order_items')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamps();
        });
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('order_returns')->nullOnDelete();
            $table->string('number', 60)->unique();
            $table->string('status', 20)->default('issued')->index();
            $table->string('reason', 255)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->unsignedBigInteger('amount');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('credit_notes'); Schema::dropIfExists('invoice_items'); Schema::dropIfExists('invoices'); }
};
