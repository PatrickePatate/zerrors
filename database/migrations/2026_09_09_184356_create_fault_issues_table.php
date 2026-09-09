<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint');
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('culprit')->nullable();
            $table->string('level')->default('error');
            $table->string('status')->default('unresolved'); // unresolved, resolved, ignored
            $table->unsignedBigInteger('times_seen')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['fault_project_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_issues');
    }
};
