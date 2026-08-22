<?php

namespace App\Services\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SchemaRepair
{
    /**
     * @return list<string>
     */
    public function ensure(): array
    {
        $notes = [];

        if (! Schema::hasTable('vtt_tables')) {
            return $notes;
        }

        if (! Schema::hasColumn('vtt_tables', 'color_template')) {
            Schema::table('vtt_tables', function (Blueprint $table) {
                $table->string('color_template', 32)->default('crimson');
            });
            $notes[] = 'Dodano brakującą kolumnę vtt_tables.color_template.';
        }

        return $notes;
    }

    /**
     * @return array<string, bool>
     */
    public function columnStatus(): array
    {
        return [
            'vtt_tables.color_template' => Schema::hasTable('vtt_tables')
                && Schema::hasColumn('vtt_tables', 'color_template'),
        ];
    }
}
