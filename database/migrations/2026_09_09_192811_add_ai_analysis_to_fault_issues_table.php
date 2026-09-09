<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->text('ai_analysis')->nullable()->after('status');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropColumn(['ai_analysis', 'ai_analyzed_at']);
        });
    }
};
