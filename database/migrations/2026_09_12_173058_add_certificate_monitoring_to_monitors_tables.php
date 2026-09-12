<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('check_certificate')->default(false)->after('expected_status_code');
            $table->unsignedSmallInteger('certificate_expiry_warning_days')->default(14)->after('check_certificate');
        });

        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->timestamp('certificate_expires_at')->nullable()->after('health_checks');
            $table->string('certificate_error')->nullable()->after('certificate_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['check_certificate', 'certificate_expiry_warning_days']);
        });

        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->dropColumn(['certificate_expires_at', 'certificate_error']);
        });
    }
};
