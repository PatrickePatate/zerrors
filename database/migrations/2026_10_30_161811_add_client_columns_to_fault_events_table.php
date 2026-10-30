<?php

use App\Models\FaultEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->string('client_ip', 45)->nullable()->index();
            $table->string('browser')->nullable()->index();
            $table->string('os')->nullable()->index();
        });

        FaultEvent::query()
            ->select(['id', 'request', 'payload'])
            ->chunkById(500, function ($events) {
                foreach ($events as $event) {
                    FaultEvent::whereKey($event->id)->update([
                        'client_ip' => $event->resolveClientIp(),
                        'browser' => $event->browserLabel(),
                        'os' => $event->osLabel(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->dropColumn(['client_ip', 'browser', 'os']);
        });
    }
};
