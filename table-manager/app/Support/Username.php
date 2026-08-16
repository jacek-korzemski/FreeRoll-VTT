<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

final class Username
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/';

    /** @var list<string> */
    public const RESERVED = [
        'admin',
        'api',
        'dashboard',
        'login',
        'manager',
        'panel',
        'public',
        'register',
        'user',
        'vtt',
        'www',
    ];

    /**
     * @return array<int, mixed>
     */
    public static function rules(?int $ignoreUserId = null): array
    {
        $unique = Rule::unique(User::class, 'username');
        if ($ignoreUserId !== null) {
            $unique = $unique->ignore($ignoreUserId);
        }

        return [
            'required',
            'string',
            'lowercase',
            'min:3',
            'max:32',
            'regex:'.self::PATTERN,
            Rule::notIn(self::RESERVED),
            $unique,
        ];
    }
}
