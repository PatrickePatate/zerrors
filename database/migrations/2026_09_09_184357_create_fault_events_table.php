<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fault_issue_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('level')->nullable();
            $table->string('message')->nullable();
            $table->string('culprit')->nullable();
            $table->string('environment')->nullable();
            $table->string('release')->nullable();
            $table->string('transaction')->nullable();
            $table->string('server_name')->nullable();
            $table->json('exception')->nullable();
            $table->json('sdk')->nullable();
            $table->json('tags')->nullable();
            $table->json('extra')->nullable();
            $table->json('contexts')->nullable();
            $table->json('request')->nullable();
            $table->json('breadcrumbs')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['fault_project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_events');
    }
};
