<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ThemeCatalog
{
    public function __construct(private ?string $sourcePath = null) {}

    public function sourcePath(): string
    {
        return rtrim($this->sourcePath ?? (string) config('vtt.source_path'), '/\\');
    }

    /**
     * @return array{default: string, themes: array<string, array{id: string, name: array{en: string, pl: string}}>}
     */
    public function catalog(): array
    {
        $path = $this->sourcePath().DIRECTORY_SEPARATOR.'themes.json';
        if (File::isFile($path)) {
            $data = json_decode(File::get($path), true);
            if (is_array($data) && isset($data['themes']) && is_array($data['themes'])) {
                $themes = [];
                foreach ($data['themes'] as $id => $theme) {
                    if (! is_string($id) || $id === '') {
                        continue;
                    }
                    $name = is_array($theme['name'] ?? null) ? $theme['name'] : [];
                    $themes[$id] = [
                        'id' => $id,
                        'name' => [
                            'en' => (string) ($name['en'] ?? $id),
                            'pl' => (string) ($name['pl'] ?? $id),
                        ],
                        'preview' => $this->previewUrl($id),
                    ];
                }
                if ($themes !== []) {
                    $default = is_string($data['default'] ?? null) ? $data['default'] : 'crimson';
                    if (! isset($themes[$default])) {
                        $default = (string) array_key_first($themes);
                    }

                    return ['default' => $default, 'themes' => $themes];
                }
            }
        }

        return [
            'default' => 'crimson',
            'themes' => [
                'crimson' => [
                    'id' => 'crimson',
                    'name' => ['en' => 'Crimson', 'pl' => 'Karmazyn'],
                    'preview' => $this->previewUrl('crimson'),
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_keys($this->catalog()['themes']));
    }

    public function defaultId(): string
    {
        return $this->catalog()['default'];
    }

    public function previewUrl(string $id): ?string
    {
        if ($id === '' || ! preg_match('/^[a-z0-9_-]+$/i', $id)) {
            return null;
        }

        $path = public_path('color-themes'.DIRECTORY_SEPARATOR.$id.'.jpg');
        if (! File::isFile($path)) {
            return null;
        }

        return asset('color-themes/'.$id.'.jpg');
    }
}
