<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vtt_tables', 'color_template')) {
            return;
        }

        Schema::table('vtt_tables', function (Blueprint $table) {
            $table->string('color_template', 32)->default('crimson');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vtt_tables', 'color_template')) {
            return;
        }

        Schema::table('vtt_tables', function (Blueprint $table) {
            $table->dropColumn('color_template');
        });
    }
};
