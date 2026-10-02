<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'max_tables')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('max_tables')->nullable();
            });
        }

        if (! Schema::hasColumn('vtt_tables', 'upload_quota_mb')) {
            Schema::table('vtt_tables', function (Blueprint $table) {
                $table->unsignedInteger('upload_quota_mb')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'max_tables')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('max_tables');
            });
        }

        if (Schema::hasColumn('vtt_tables', 'upload_quota_mb')) {
            Schema::table('vtt_tables', function (Blueprint $table) {
                $table->dropColumn('upload_quota_mb');
            });
        }
    }
};
