<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->string('github_repo')->nullable()->after('retention_days');
            $table->text('github_token')->nullable()->after('github_repo');
        });
    }

    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn(['github_repo', 'github_token']);
        });
    }
};
