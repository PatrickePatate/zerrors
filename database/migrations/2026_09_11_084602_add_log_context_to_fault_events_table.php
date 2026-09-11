<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->json('log_context')->nullable()->after('breadcrumbs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fault_events', function (Blueprint $table) {
            $table->dropColumn('log_context');
        });
    }
};
