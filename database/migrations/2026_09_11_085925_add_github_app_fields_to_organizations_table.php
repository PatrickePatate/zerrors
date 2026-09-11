<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('github_installation_id')->nullable();
            $table->string('github_account_login')->nullable();
            $table->string('github_account_type')->nullable();
            $table->timestamp('github_connected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'github_installation_id',
                'github_account_login',
                'github_account_type',
                'github_connected_at',
            ]);
        });
    }
};
