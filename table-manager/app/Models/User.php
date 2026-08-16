<?php

namespace App\Models;

use App\Services\TableProvisioner;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function vttTables(): HasMany
    {
        return $this->hasMany(VttTable::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $provisioner = app(TableProvisioner::class);
            $user->vttTables()->with('user')->get()->each(
                fn (VttTable $table) => $provisioner->removeFiles($table)
            );
        });
    }
}
