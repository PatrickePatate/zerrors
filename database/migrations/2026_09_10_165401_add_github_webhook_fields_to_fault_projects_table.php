<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->string('production_branch')->default('main')->after('github_token');
            $table->text('github_webhook_secret')->nullable()->after('production_branch');
        });
    }

    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn(['production_branch', 'github_webhook_secret']);
        });
    }
};
