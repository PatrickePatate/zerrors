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
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->json('censored_headers')->nullable()->after('forward_dsn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fault_projects', function (Blueprint $table) {
            $table->dropColumn('censored_headers');
        });
    }
};
