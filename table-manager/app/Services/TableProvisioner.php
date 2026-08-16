<?php

namespace App\Services;

use App\Exceptions\VttSourceMissingException;
use App\Models\User;
use App\Models\VttTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableProvisioner
{
    public function sourcePath(): string
    {
        return rtrim((string) config('vtt.source_path'), '/\\');
    }

    public function sourceIsReady(): bool
    {
        $source = $this->sourcePath();

        return File::isFile($source.DIRECTORY_SEPARATOR.'index.php')
            && File::isFile($source.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js');
    }

    public function assertSourceReady(): void
    {
        if (! $this->sourceIsReady()) {
            throw VttSourceMissingException::make($this->sourcePath());
        }
    }

    /**
     * @param  array{name: string, player_password: string, gm_password: string, language: string}  $data
     */
    public function create(User $user, array $data): VttTable
    {
        $this->assertSourceReady();

        return DB::transaction(function () use ($user, $data) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->vttTables()->count() >= (int) config('vtt.max_tables')) {
                throw ValidationException::withMessages([
                    'name' => 'Możesz mieć maksymalnie '.config('vtt.max_tables').' stoły.',
                ]);
            }

            $table = $locked->vttTables()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug(),
                'player_password' => $data['player_password'],
                'gm_password' => $data['gm_password'],
                'language' => $data['language'],
            ]);

            $table->setRelation('user', $locked);

            try {
                $this->provisionFiles($table);
            } catch (\Throwable $e) {
                $this->removeFiles($table);
                throw $e;
            }

            return $table;
        });
    }

    public function updateSettings(VttTable $table, array $data): VttTable
    {
        $table->fill($data);
        $table->save();
        $table->loadMissing('user');
        $this->writeEnv($table);

        return $table;
    }

    public function destroy(VttTable $table): void
    {
        $table->loadMissing('user');
        $this->removeFiles($table);
        $table->delete();
    }

    public function removeFiles(VttTable $table): void
    {
        $dir = $table->absolutePath();
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }

        $parent = dirname($dir);
        if (File::isDirectory($parent) && $this->isEmptyDirectory($parent)) {
            File::deleteDirectory($parent);
        }
    }

    public function provisionFiles(VttTable $table): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $dest = $table->absolutePath();
        File::ensureDirectoryExists(dirname($dest));

        if (File::isDirectory($dest)) {
            File::deleteDirectory($dest);
        }

        File::copyDirectory($this->sourcePath(), $dest);

        $dataDir = $dest.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data';
        File::ensureDirectoryExists($dataDir);
        foreach (['state.json', 'rolls.json'] as $file) {
            $path = $dataDir.DIRECTORY_SEPARATOR.$file;
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $this->writeEnv($table);
    }

    public function writeEnv(VttTable $table): void
    {
        $dest = $table->absolutePath();
        File::ensureDirectoryExists($dest);

        $content = implode("\n", [
            'VTT_PASSWORD='.$table->player_password,
            'VTT_GM_PASSWORD='.$table->gm_password,
            'VTT_BASE_PATH='.$table->publicPath(),
            'VTT_LANGUAGE='.$table->language,
            'VTT_ENABLE_L5R='.$this->sourceEnableL5r(),
            'ALLOWED_ORIGINS='.config('vtt.allowed_origins'),
        ])."\n";

        File::put($dest.DIRECTORY_SEPARATOR.'.env', $content);
    }

    private function uniqueSlug(): string
    {
        $length = (int) config('vtt.slug_length', 10);

        do {
            $slug = Str::lower(Str::random($length));
        } while (VttTable::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function sourceEnableL5r(): string
    {
        $envPath = $this->sourcePath().DIRECTORY_SEPARATOR.'.env';
        if (! File::isFile($envPath)) {
            return 'false';
        }

        foreach (File::lines($envPath) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            if (trim($key) === 'VTT_ENABLE_L5R') {
                $raw = strtolower(trim($value, " \t\"'"));

                return ($raw === 'true' || $raw === '1') ? 'true' : 'false';
            }
        }

        return 'false';
    }

    private function isEmptyDirectory(string $path): bool
    {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);

        return $items === [];
    }
}
