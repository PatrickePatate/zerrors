<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('commit_sha')->nullable()->after('notes');
            $table->string('commit_url')->nullable()->after('commit_sha');
            $table->text('commit_message')->nullable()->after('commit_url');
            $table->string('commit_author')->nullable()->after('commit_message');
            $table->timestamp('committed_at')->nullable()->after('commit_author');
            $table->json('commit_files')->nullable()->after('committed_at');
            $table->unsignedInteger('commit_additions')->nullable()->after('commit_files');
            $table->unsignedInteger('commit_deletions')->nullable()->after('commit_additions');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn([
                'commit_sha', 'commit_url', 'commit_message', 'commit_author',
                'committed_at', 'commit_files', 'commit_additions', 'commit_deletions',
            ]);
        });
    }
};
