<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('organization_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('action')->after('user_id');
            $table->string('subject_type')->nullable()->after('action');
            $table->string('subject_label')->nullable()->after('subject_type');
            $table->json('meta')->nullable()->after('subject_label');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'user_id', 'action', 'subject_type', 'subject_label', 'meta']);
        });
    }
};
