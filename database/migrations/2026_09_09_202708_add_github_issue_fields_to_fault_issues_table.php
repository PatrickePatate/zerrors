<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->string('github_issue_url')->nullable()->after('ai_analyzed_at');
            $table->unsignedInteger('github_issue_number')->nullable()->after('github_issue_url');
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropColumn(['github_issue_url', 'github_issue_number']);
        });
    }
};
