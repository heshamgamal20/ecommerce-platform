<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carrier_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('carrier', 191);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 3)->default('EGP');
            $table->unsignedBigInteger('gross_cod_amount')->default(0);
            $table->unsignedBigInteger('shipping_fees')->default(0);
            $table->unsignedBigInteger('return_fees')->default(0);
            $table->bigInteger('other_adjustments')->default(0);
            $table->bigInteger('expected_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->string('status', 20)->default('open')->index();
            $table->string('provider_reference', 191)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['carrier', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_settlements');
    }
};
