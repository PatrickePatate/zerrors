<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('slack_team_id')->nullable();
            $table->string('slack_team_name')->nullable();
            $table->text('slack_bot_token')->nullable();
            $table->string('slack_authed_user_id')->nullable();
            $table->timestamp('slack_connected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'slack_team_id',
                'slack_team_name',
                'slack_bot_token',
                'slack_authed_user_id',
                'slack_connected_at',
            ]);
        });
    }
};
