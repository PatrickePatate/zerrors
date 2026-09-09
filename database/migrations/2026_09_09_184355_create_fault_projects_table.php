<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_projects', function (Blueprint $table) {
            $table->id(); // this is the {project_id} segment in the DSN path
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('public_key')->unique();
            $table->string('secret_key')->nullable();
            $table->unsignedInteger('retention_days')->default(90);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fault_projects');
    }
};
