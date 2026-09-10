<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->text('ai_deep_analysis')->nullable()->after('ai_analyzed_at');
            $table->timestamp('ai_deep_analyzed_at')->nullable()->after('ai_deep_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('fault_issues', function (Blueprint $table) {
            $table->dropColumn(['ai_deep_analysis', 'ai_deep_analyzed_at']);
        });
    }
};
