<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('auto')->default(false);
            $table->timestamps();

            $table->index(['status_page_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incidents');
    }
};
