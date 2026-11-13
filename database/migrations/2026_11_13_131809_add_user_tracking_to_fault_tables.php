<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->boolean('latest_event_handled')->nullable();
            $table->unsignedInteger('users_affected')->default(0);
            $table->index(['fault_project_id', 'latest_event_handled'], 'fault_issues_project_handled_index');
        });

        Schema::table('fault_events', function (Blueprint $table) {
            $table->string('user_identifier')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->dropColumn('user_identifier');
        });

        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropIndex('fault_issues_project_handled_index');
            $table->dropColumn(['latest_event_handled', 'users_affected']);
        });
    }
};
