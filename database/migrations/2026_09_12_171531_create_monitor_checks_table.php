<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('health_checks')->nullable();
            $table->timestamp('checked_at');
            $table->index(['monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_checks');
    }
};
