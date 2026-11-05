<?php

namespace App\Services;

use App\Exceptions\TableUpdateException;
use App\Models\VttTable;
use Illuminate\Support\Facades\File;

class TableUpdater
{
    public const LOCK_TTL_SECONDS = 600;

    /**
     * Code directories replaced as a whole, so old hashed frontend files do not linger.
     *
     * @var list<string>
     */
    public const DIRECTORY_PATHS = [
        'assets',
        'backend/src',
        'backend/include',
        'backend/vendor',
    ];

    /**
     * Code files overwritten from the package. VERSION is always applied last.
     *
     * @var list<string>
     */
    public const FILE_PATHS = [
        'index.php',
        'deploy-env.php',
        'themes.json',
        '.htaccess',
        'backend/api.php',
        'backend/composer.json',
        'backend/composer.lock',
        'backend/.htaccess',
        'VERSION',
    ];

    /**
     * Player data. An update must not replace, delete, or copy over these paths.
     * Add a new player-data directory here before bumping VERSION.
     *
     * @var list<string>
     */
    public const PRESERVED_PATHS = [
        '.env',
        'backend/data',
        'backend/assets',
    ];

    /** @var resource|null */
    private mixed $lockHandle = null;

    public function __construct(private TableProvisioner $provisioner) {}

    public function sourceVersion(): int
    {
        return $this->readVersion($this->provisioner->sourcePath());
    }

    public function installedVersion(VttTable $table): int
    {
        return $this->readVersion($table->absolutePath());
    }

    public function updateAvailable(VttTable $table): bool
    {
        if (! $this->provisioner->sourceIsReady()) {
            return false;
        }

        $available = $this->sourceVersion();

        return $available > 0 && $available > $this->installedVersion($table);
    }

    public function update(VttTable $table): void
    {
        $this->provisioner->assertSourceReady();

        $source = $this->provisioner->sourcePath();
        $dest = $table->absolutePath();

        if (! is_dir($dest)) {
            throw new TableUpdateException('Nie znaleziono katalogu stołu.');
        }

        $available = $this->readVersion($source);
        $installed = $this->readVersion($dest);
        if ($available <= $installed) {
            throw new TableUpdateException('Stół jest już w tej wersji.');
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $ownedLock = false;
        $plan = [];

        try {
            $this->acquireLock($dest);
            $ownedLock = true;
            $this->recoverLeftovers($dest);
            $plan = $this->pathsToReplace($source);
            $this->copyIncoming($source, $dest, $plan);
            $this->swapIn($dest, $plan);
            $this->removeBackups($dest, $plan);
            $this->ensureAssetDirectories($source, $dest);
        } finally {
            if ($ownedLock) {
                $this->cleanupIncoming($dest, $plan);
                $this->releaseLock($dest);
            }
        }
    }

    public function readVersion(string $root): int
    {
        $path = $this->join($root, 'VERSION');
        if (! is_file($path)) {
            return 0;
        }

        $raw = trim((string) File::get($path));
        if (preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            return 0;
        }

        return (int) $raw;
    }

    /**
     * @param  list<string>  $plan
     */
    private function copyIncoming(string $source, string $dest, array $plan): void
    {
        foreach ($plan as $relative) {
            $from = $this->join($source, $relative);
            $to = $this->join($dest, $relative).'.__new';
            $this->deletePath($to);
            File::ensureDirectoryExists(dirname($to));

            if (is_dir($from)) {
                if (! File::copyDirectory($from, $to)) {
                    throw new TableUpdateException('Nie udało się skopiować plików aktualizacji. Zamknij stół i spróbuj ponownie.');
                }

                continue;
            }

            if (! File::copy($from, $to)) {
                throw new TableUpdateException('Nie udało się skopiować plików aktualizacji. Zamknij stół i spróbuj ponownie.');
            }
        }
    }

    /**
     * @param  list<string>  $plan
     */
    private function swapIn(string $dest, array $plan): void
    {
        $swapped = [];

        try {
            foreach ($plan as $relative) {
                $this->swapOne($dest, $relative);
                $swapped[] = $relative;
            }
        } catch (TableUpdateException) {
            try {
                $this->rollback($dest, $swapped);
            } catch (TableUpdateException) {
                throw new TableUpdateException('Nie udało się podmienić plików stołu. Zamknij stół i spróbuj ponownie. Cofnięcie podmiany też się nie udało.');
            }

            throw new TableUpdateException('Nie udało się podmienić plików stołu. Zamknij stół i spróbuj ponownie.');
        }
    }

    private function swapOne(string $dest, string $relative): void
    {
        $live = $this->join($dest, $relative);
        $incoming = $live.'.__new';
        $backup = $live.'.__old';

        if (File::exists($live)) {
            $this->movePath($live, $backup);
        }

        try {
            $this->movePath($incoming, $live);
        } catch (TableUpdateException $e) {
            if (File::exists($backup) && ! File::exists($live)) {
                $this->movePath($backup, $live);
            }

            throw $e;
        }
    }

    /**
     * @param  list<string>  $swapped
     */
    private function rollback(string $dest, array $swapped): void
    {
        foreach (array_reverse($swapped) as $relative) {
            $live = $this->join($dest, $relative);
            $backup = $live.'.__old';
            $discard = $live.'.__discard';

            if (File::exists($live) && File::exists($backup)) {
                $this->movePath($live, $discard);
                $this->movePath($backup, $live);
                $this->deletePath($discard);

                continue;
            }

            if (! File::exists($live) && File::exists($backup)) {
                $this->movePath($backup, $live);
            }
        }
    }

    /**
     * @param  list<string>  $plan
     */
    private function removeBackups(string $dest, array $plan): void
    {
        foreach ($plan as $relative) {
            $this->deletePath($this->join($dest, $relative).'.__old');
        }
    }

    /**
     * @param  list<string>  $plan
     */
    private function cleanupIncoming(string $dest, array $plan): void
    {
        foreach ($plan as $relative) {
            $this->deletePath($this->join($dest, $relative).'.__new');
        }
    }

    private function recoverLeftovers(string $dest): void
    {
        foreach (array_merge(self::DIRECTORY_PATHS, self::FILE_PATHS) as $relative) {
            $live = $this->join($dest, $relative);
            $backup = $live.'.__old';
            $incoming = $live.'.__new';

            if (! File::exists($live) && File::exists($backup)) {
                $this->movePath($backup, $live);
            }

            if (File::exists($incoming)) {
                $this->deletePath($incoming);
            }

            if (File::exists($live) && File::exists($backup)) {
                $this->deletePath($backup);
            }
        }
    }

    private function ensureAssetDirectories(string $source, string $dest): void
    {
        $assets = $this->join($source, 'backend/assets');
        if (! is_dir($assets)) {
            return;
        }

        $entries = scandir($assets);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\')) {
                continue;
            }

            if (! is_dir($assets.DIRECTORY_SEPARATOR.$name)) {
                continue;
            }

            File::ensureDirectoryExists($this->join($dest, 'backend/assets').DIRECTORY_SEPARATOR.$name);
        }
    }

    /**
     * @return list<string>
     */
    private function pathsToReplace(string $source): array
    {
        $paths = [];

        foreach (array_merge(self::DIRECTORY_PATHS, self::FILE_PATHS) as $relative) {
            if ($relative === 'VERSION') {
                continue;
            }

            $full = $this->join($source, $relative);
            if (is_dir($full) || is_file($full)) {
                $paths[] = $relative;
            }
        }

        if (! is_file($this->join($source, 'VERSION'))) {
            throw new TableUpdateException('Paczka nie ma numeru wersji.');
        }

        $paths[] = 'VERSION';
        $this->assertPlanSafe($paths);

        return $paths;
    }

    /**
     * @param  list<string>  $plan
     */
    private function assertPlanSafe(array $plan): void
    {
        foreach ($plan as $relative) {
            foreach (self::PRESERVED_PATHS as $preserved) {
                if ($this->pathsOverlap($relative, $preserved)) {
                    throw new TableUpdateException('Aktualizacja próbowała ruszyć dane stołu.');
                }
            }
        }
    }

    private function pathsOverlap(string $left, string $right): bool
    {
        return $left === $right
            || str_starts_with($left, $right.'/')
            || str_starts_with($right, $left.'/');
    }

    protected function movePath(string $from, string $to): void
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $moved = File::move($from, $to);
        } finally {
            restore_error_handler();
        }

        if ($moved !== true) {
            throw new TableUpdateException('Nie udało się podmienić plików stołu. Zamknij stół i spróbuj ponownie.');
        }
    }

    private function acquireLock(string $dest): void
    {
        $path = $dest.DIRECTORY_SEPARATOR.'update.lock';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new TableUpdateException('Nie udało się rozpocząć aktualizacji stołu.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new TableUpdateException('Aktualizacja tego stołu już trwa. Spróbuj za chwilę.');
        }

        rewind($handle);
        $raw = trim((string) stream_get_contents($handle));
        $started = ctype_digit($raw) ? (int) $raw : 0;
        if ($started > 0 && (time() - $started) < self::LOCK_TTL_SECONDS) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new TableUpdateException('Aktualizacja tego stołu już trwa. Spróbuj za chwilę.');
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) time());
        fflush($handle);
        $this->lockHandle = $handle;
    }

    private function releaseLock(string $dest): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }

        $path = $dest.DIRECTORY_SEPARATOR.'update.lock';
        if (is_file($path)) {
            File::delete($path);
        }
    }

    private function deletePath(string $path): void
    {
        if (is_dir($path)) {
            File::deleteDirectory($path);

            return;
        }

        if (is_file($path)) {
            File::delete($path);
        }
    }

    private function join(string $root, string $relative): string
    {
        $relative = str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.$relative;
    }
}
