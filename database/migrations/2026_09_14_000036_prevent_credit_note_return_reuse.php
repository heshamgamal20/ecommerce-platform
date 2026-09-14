<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->unique('return_id', 'credit_notes_return_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->dropUnique('credit_notes_return_id_unique');
        });
    }
};
