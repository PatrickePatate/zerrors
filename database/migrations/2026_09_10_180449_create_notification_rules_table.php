<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->json('thresholds')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['notification_channel_id', 'trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
