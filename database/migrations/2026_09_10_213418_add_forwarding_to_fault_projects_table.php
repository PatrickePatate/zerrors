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
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->boolean('forward_enabled')->default(false)->after('github_webhook_secret');
            $table->text('forward_dsn')->nullable()->after('forward_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn(['forward_enabled', 'forward_dsn']);
        });
    }
};
