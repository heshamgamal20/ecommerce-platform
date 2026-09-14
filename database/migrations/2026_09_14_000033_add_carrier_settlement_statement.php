<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carrier_settlements', function (Blueprint $table): void {
            $table->string('statement_file_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('carrier_settlement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('carrier_settlement_id')->constrained('carrier_settlements')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('tracking_number', 191);
            $table->string('carrier_status', 60)->nullable();
            $table->unsignedBigInteger('expected_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->string('match_status', 30)->default('matched')->index();
            $table->string('difference_reason', 191)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['carrier_settlement_id', 'tracking_number']);
            $table->index(['tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_settlement_lines');
        Schema::table('carrier_settlements', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['statement_file_name', 'approved_at', 'approved_by', 'locked_at', 'locked_by']);
        });
    }
};
