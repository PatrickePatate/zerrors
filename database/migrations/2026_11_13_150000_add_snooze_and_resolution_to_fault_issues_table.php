<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->timestamp('snoozed_until')->nullable();
            $table->unsignedBigInteger('snooze_until_count')->nullable();
            $table->string('resolved_in_release')->nullable();
            $table->boolean('resolved_in_next_release')->default(false);
            $table->string('resolved_last_seen_release')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['status', 'snoozed_until']);
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropIndex(['status', 'snoozed_until']);
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['snoozed_until', 'snooze_until_count', 'resolved_in_release', 'resolved_in_next_release', 'resolved_last_seen_release', 'resolved_at']);
        });
    }
};
