<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $projects = DB::table('fault_projects')
            ->select('id', 'slack_webhook_url', 'telegram_bot_token', 'telegram_chat_id', 'notify_email')
            ->get();

        foreach ($projects as $project) {
            if (! empty($project->slack_webhook_url)) {
                $this->createChannel($project->id, 'slack', 'Slack', [
                    'webhook_url' => $project->slack_webhook_url,
                ]);
            }

            if (! empty($project->telegram_bot_token) && ! empty($project->telegram_chat_id)) {
                $this->createChannel($project->id, 'telegram', 'Telegram', [
                    'bot_token' => Crypt::decryptString($project->telegram_bot_token),
                    'chat_id' => $project->telegram_chat_id,
                ]);
            }

            if (! empty($project->notify_email)) {
                $this->createChannel($project->id, 'email', 'Email', [
                    'email' => $project->notify_email,
                ]);
            }
        }

        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn(['slack_webhook_url', 'telegram_bot_token', 'telegram_chat_id', 'notify_email']);
        });
    }

    /**
     * @param  array<string, string>  $config
     */
    protected function createChannel(int $projectId, string $type, string $name, array $config): void
    {
        $channelId = DB::table('notification_channels')->insertGetId([
            'fault_project_id' => $projectId,
            'type' => $type,
            'name' => $name,
            'config' => Crypt::encryptString(json_encode($config)),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['new_issue', 'regression'] as $trigger) {
            DB::table('notification_rules')->insert([
                'notification_channel_id' => $channelId,
                'trigger' => $trigger,
                'thresholds' => null,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->text('slack_webhook_url')->nullable();
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->string('notify_email')->nullable();
        });
    }
};
