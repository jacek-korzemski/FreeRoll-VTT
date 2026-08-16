<?php

namespace App\Exceptions;

use RuntimeException;

class VttSourceMissingException extends RuntimeException
{
    public static function make(string $sourcePath): self
    {
        return new self(
            'Brak paczki VTT w current-source (oczekiwane: index.php oraz assets/index.js). Ścieżka: '.$sourcePath
        );
    }
}
