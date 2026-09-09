<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fault_project_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->text('notes')->nullable();
            $table->timestamp('deployed_at');
            $table->timestamps();

            $table->unique(['fault_project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
