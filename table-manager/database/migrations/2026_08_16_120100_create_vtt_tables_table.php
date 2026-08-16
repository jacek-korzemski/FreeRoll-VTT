<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtt_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 16)->unique();
            $table->text('player_password');
            $table->text('gm_password');
            $table->string('language', 2)->default('pl');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtt_tables');
    }
};
