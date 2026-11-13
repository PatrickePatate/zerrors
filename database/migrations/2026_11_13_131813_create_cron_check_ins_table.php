<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cron_monitor_id')->constrained()->cascadeOnDelete();
            $table->string('check_in_id', 64)->nullable();
            $table->string('status');
            $table->double('duration')->nullable();
            $table->string('environment')->nullable();
            $table->timestamps();

            $table->index(['cron_monitor_id', 'created_at']);
            $table->index(['cron_monitor_id', 'check_in_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_check_ins');
    }
};
