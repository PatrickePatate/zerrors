<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_issue_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint');
            $table->foreignId('fault_issue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fault_project_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_issue_fingerprints');
    }
};
