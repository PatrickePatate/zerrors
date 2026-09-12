<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // Encrypted at rest (like FaultProject's secret_key/forward_dsn) since
            // these headers commonly carry bearer tokens or basic-auth credentials
            // for the monitored endpoint.
            $table->text('headers')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('headers');
        });
    }
};
