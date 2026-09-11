<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The GitHub App integration replaces per-project personal access tokens
     * and webhook secrets with an org-wide installation. This is pre-production
     * data, so losing these encrypted values on rollback is acceptable.
     */
    public function up(): void
    {
        Schema::table('fault_projects', function (Blueprint $table): void {
            $table->dropColumn(['github_token', 'github_webhook_secret']);
        });
    }

    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table): void {
            $table->text('github_token')->nullable();
            $table->text('github_webhook_secret')->nullable();
        });
    }
};
