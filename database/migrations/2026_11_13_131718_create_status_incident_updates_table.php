<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->text('body');
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incident_updates');
    }
};
