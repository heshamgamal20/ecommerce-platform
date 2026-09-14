<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspection_status', 20)->default('pending')->index();
            $table->text('inspection_notes')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('final_refund_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            $table->dropForeign(['received_by']);
            $table->dropForeign(['inspected_by']);
            $table->dropColumn(['received_at', 'received_by', 'inspection_status', 'inspection_notes', 'inspected_at', 'inspected_by', 'final_refund_amount']);
        });
    }
};
