<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->index(['fault_issue_id', 'occurred_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->dropIndex(['fault_issue_id', 'occurred_at', 'id']);
        });
    }
};
