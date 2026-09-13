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
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->unsignedInteger('dns_time_ms')->nullable()->after('response_time_ms');
            $table->unsignedInteger('connect_time_ms')->nullable()->after('dns_time_ms');
            $table->unsignedInteger('ssl_time_ms')->nullable()->after('connect_time_ms');
            $table->unsignedInteger('ttfb_ms')->nullable()->after('ssl_time_ms');
            $table->unsignedInteger('download_time_ms')->nullable()->after('ttfb_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->dropColumn(['dns_time_ms', 'connect_time_ms', 'ssl_time_ms', 'ttfb_ms', 'download_time_ms']);
        });
    }
};
