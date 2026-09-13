<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->timestamp('dead_lettered_at')->nullable()->after('dispatched_at');
            $table->index(['status', 'attempt_count']);
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropIndex(['status', 'attempt_count']);
            $table->dropColumn('dead_lettered_at');
        });
    }
};
