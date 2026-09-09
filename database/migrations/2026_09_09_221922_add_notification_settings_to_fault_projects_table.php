<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->text('slack_webhook_url')->nullable()->after('github_token');
            $table->text('telegram_bot_token')->nullable()->after('slack_webhook_url');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_token');
            $table->string('notify_email')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn(['slack_webhook_url', 'telegram_bot_token', 'telegram_chat_id', 'notify_email']);
        });
    }
};
