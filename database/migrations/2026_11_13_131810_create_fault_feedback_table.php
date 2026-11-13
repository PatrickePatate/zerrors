<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fault_issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fault_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id', 36)->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('comments');
            $table->timestamps();

            $table->index(['fault_project_id', 'event_id']);
            $table->index('fault_issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_feedback');
    }
};
