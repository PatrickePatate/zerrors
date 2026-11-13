<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('schedule_type')->nullable();
            $table->string('schedule_value')->nullable();
            $table->string('schedule_unit')->nullable();
            $table->unsignedInteger('checkin_margin')->default(5);
            $table->unsignedInteger('max_runtime')->default(60);
            $table->string('timezone')->default('UTC');
            $table->string('status')->default('unknown');
            $table->timestamp('last_check_in_at')->nullable();
            $table->timestamp('next_expected_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['fault_project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_monitors');
    }
};
