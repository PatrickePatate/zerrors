<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_checks');
            $table->unsignedInteger('up_count');
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->unsignedInteger('avg_dns_time_ms')->nullable();
            $table->unsignedInteger('avg_connect_time_ms')->nullable();
            $table->unsignedInteger('avg_ssl_time_ms')->nullable();
            $table->unsignedInteger('avg_ttfb_ms')->nullable();
            $table->unsignedInteger('avg_download_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['monitor_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_daily_stats');
    }
};
