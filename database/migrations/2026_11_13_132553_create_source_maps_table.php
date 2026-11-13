<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->string('release');
            $table->string('artifact_name', 1024);
            $table->string('artifact_hash', 40);
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('sha1', 40);
            $table->timestamps();

            $table->unique(['fault_project_id', 'release', 'artifact_hash']);
            $table->index(['fault_project_id', 'release']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_maps');
    }
};
