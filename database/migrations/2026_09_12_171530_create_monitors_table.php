<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('fault_projects')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('url');
            $table->unsignedSmallInteger('check_interval_minutes')->default(5);
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('expected_status_code')->nullable()->default(200);
            $table->boolean('is_active')->default(true);
            $table->string('current_status')->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
