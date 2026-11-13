<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->string('sha', 64);
            $table->text('message')->nullable();
            $table->string('author')->nullable();
            $table->string('url')->nullable();
            $table->json('files')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['release_id', 'sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_commits');
    }
};
