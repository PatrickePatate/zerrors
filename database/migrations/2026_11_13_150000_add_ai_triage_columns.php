<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->string('triage_category')->nullable();
            $table->string('triage_priority')->nullable();
            $table->string('triage_model')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->string('triage_error', 500)->nullable();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('ai_triage_enabled')->default(false);
            $table->string('ai_triage_model')->nullable();
            $table->text('ai_triage_api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropColumn(['triage_category', 'triage_priority', 'triage_model', 'triaged_at', 'triage_error']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['ai_triage_enabled', 'ai_triage_model', 'ai_triage_api_key']);
        });
    }
};
