<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->string('first_seen_release')->nullable()->after('regressed_at');
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropColumn('first_seen_release');
        });
    }
};
