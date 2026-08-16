<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'slug', 'player_password', 'gm_password', 'language'])]
class VttTable extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'player_password' => 'encrypted',
            'gm_password' => 'encrypted',
        ];
    }

    public function publicPath(): string
    {
        return '/vtt/user/'.$this->user->username.'/'.$this->slug.'/';
    }

    public function publicUrl(): string
    {
        return url($this->publicPath());
    }

    public function absolutePath(): string
    {
        return rtrim((string) config('vtt.tables_path'), '/\\')
            .DIRECTORY_SEPARATOR.$this->user->username
            .DIRECTORY_SEPARATOR.$this->slug;
    }
}
