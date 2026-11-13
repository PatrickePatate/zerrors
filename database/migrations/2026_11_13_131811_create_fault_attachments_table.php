<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fault_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_id', 36);
            $table->string('filename');
            $table->string('content_type')->nullable();
            $table->string('attachment_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->timestamps();

            $table->index(['fault_project_id', 'event_id']);
            $table->index('fault_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_attachments');
    }
};
