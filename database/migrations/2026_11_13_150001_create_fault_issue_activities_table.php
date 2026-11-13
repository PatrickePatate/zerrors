<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_issue_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['fault_issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_issue_activities');
    }
};
