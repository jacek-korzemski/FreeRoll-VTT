<?php

namespace App\Services\Admin;

use App\Models\VttTable;
use Illuminate\Support\Facades\File;

class TableDiskReader
{
    public const IDLE_SECONDS = 45;

    /** @var list<string> */
    public const ASSET_TYPES = ['tokens', 'map', 'backgrounds', 'papers', 'templates'];

    public function telemetryDir(VttTable $table): string
    {
        return $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'telemetry';
    }

    public function dataDir(VttTable $table): string
    {
        return $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data';
    }

    public function assetsDir(VttTable $table): string
    {
        return $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets';
    }

    /**
     * @return array<string, mixed>
     */
    public function telemetry(VttTable $table, ?int $now = null): array
    {
        $now = $now ?? time();
        $dir = $this->telemetryDir($table);
        $presence = $this->readJson($dir.DIRECTORY_SEPARATOR.'presence.json', [
            'updatedAt' => 0,
            'openSession' => null,
            'clients' => [],
        ]);
        $sessions = $this->readJson($dir.DIRECTORY_SEPARATOR.'sessions.json', []);
        if (! is_array($sessions)) {
            $sessions = [];
        }

        $clients = is_array($presence['clients'] ?? null) ? $presence['clients'] : [];
        $online = [];
        $lastSeen = null;
        foreach ($clients as $id => $client) {
            if (! is_array($client)) {
                continue;
            }
            $seen = (int) ($client['lastSeen'] ?? 0);
            if ($seen > 0) {
                $lastSeen = $lastSeen === null ? $seen : max($lastSeen, $seen);
            }
            if ($seen > 0 && ($now - $seen) < self::IDLE_SECONDS) {
                $online[] = $client + ['clientId' => $client['clientId'] ?? (string) $id];
            }
        }

        $open = is_array($presence['openSession'] ?? null) ? $presence['openSession'] : null;
        if ($open && $online === []) {
            $started = (int) ($open['startedAt'] ?? $now);
            $ended = $lastSeen ?? $now;
            if ($ended < $started) {
                $ended = $started;
            }
            $sessions[] = [
                'startedAt' => $started,
                'endedAt' => $ended,
                'durationSec' => max(0, $ended - $started),
                'peakClients' => (int) ($open['peakClients'] ?? 1),
                'clientIds' => array_values($open['clientIds'] ?? []),
                'open' => false,
                'inferredClose' => true,
            ];
            $open = null;
        } elseif ($open) {
            $open['open'] = true;
            $open['durationSec'] = max(0, $now - (int) ($open['startedAt'] ?? $now));
        }

        $events = $this->readJsonl($dir.DIRECTORY_SEPARATOR.'events.jsonl');
        $logins = ['player' => 0, 'gm' => 0, 'fail' => 0];
        $since24h = $now - 86400;
        $interactions24h = 0;
        $actionCounts = [];
        $hourly = array_fill(0, 24, 0);

        foreach ($events as $event) {
            $ts = (int) ($event['ts'] ?? 0);
            $type = (string) ($event['type'] ?? '');
            if ($type === 'login') {
                $role = ($event['role'] ?? 'player') === 'gm' ? 'gm' : 'player';
                $logins[$role]++;
            } elseif ($type === 'login_fail') {
                $logins['fail']++;
            } elseif ($type === 'interaction') {
                $action = (string) ($event['action'] ?? 'other');
                $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
                if ($ts >= $since24h) {
                    $interactions24h++;
                    $hour = (int) gmdate('G', $ts);
                    $hourly[$hour]++;
                }
            }
        }

        $sessionSeconds = 0;
        foreach ($sessions as $session) {
            if (is_array($session)) {
                $sessionSeconds += (int) ($session['durationSec'] ?? 0);
            }
        }
        if (is_array($open)) {
            $sessionSeconds += (int) ($open['durationSec'] ?? 0);
        }

        arsort($actionCounts);

        return [
            'clients' => array_values($clients),
            'onlineClients' => $online,
            'onlineCount' => count($online),
            'lastSeen' => $lastSeen,
            'openSession' => $open,
            'sessions' => $sessions,
            'events' => $events,
            'uniqueClients' => count($clients),
            'logins' => $logins,
            'logins24h' => $this->countEventsSince($events, ['login'], $since24h),
            'interactions24h' => $interactions24h,
            'actionCounts' => $actionCounts,
            'hourly24' => $hourly,
            'sessionSecondsTotal' => $sessionSeconds,
        ];
    }

    /**
     * @return list<array{type: string, name: string, relative: string, size: int, mtime: int, mime: string, isImage: bool}>
     */
    public function listAssets(VttTable $table): array
    {
        $root = $this->assetsDir($table);
        $out = [];
        foreach (self::ASSET_TYPES as $type) {
            $dir = $root.DIRECTORY_SEPARATOR.$type;
            if (! File::isDirectory($dir)) {
                continue;
            }
            $this->walkAssets($dir, $type, $out);
        }
        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    /**
     * @return array{json: mixed, raw: string, mtime: ?int, exists: bool}
     */
    public function readStateFile(VttTable $table, string $name): array
    {
        $allowed = ['state.json', 'rolls.json'];
        if (! in_array($name, $allowed, true)) {
            return ['json' => null, 'raw' => '', 'mtime' => null, 'exists' => false];
        }
        $path = $this->dataDir($table).DIRECTORY_SEPARATOR.$name;
        if (! File::isFile($path)) {
            return ['json' => null, 'raw' => '', 'mtime' => null, 'exists' => false];
        }
        $raw = (string) File::get($path);

        return [
            'json' => json_decode($raw, true),
            'raw' => $raw,
            'mtime' => File::lastModified($path),
            'exists' => true,
        ];
    }

    public function resolveAssetPath(VttTable $table, string $relative): ?string
    {
        $relative = str_replace(['\\', "\0"], ['/', ''], $relative);
        $relative = ltrim($relative, '/');
        $parts = explode('/', $relative);
        if ($parts === [] || ! in_array($parts[0], self::ASSET_TYPES, true)) {
            return null;
        }
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }
        $full = $this->assetsDir($table).DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);
        $root = realpath($this->assetsDir($table));
        $real = realpath($full);
        if ($root === false || $real === false) {
            return null;
        }
        if (! str_starts_with($real, $root)) {
            return null;
        }
        if (! is_file($real)) {
            return null;
        }

        return $real;
    }

    public function deleteAsset(VttTable $table, string $relative): bool
    {
        $path = $this->resolveAssetPath($table, $relative);
        if ($path === null) {
            return false;
        }

        return File::delete($path);
    }

    public function resetGameState(VttTable $table): void
    {
        $dir = $this->dataDir($table);
        File::ensureDirectoryExists($dir);
        foreach (['state.json', 'rolls.json'] as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (File::isFile($path)) {
                File::delete($path);
            }
        }
    }

    public function destroyTable(VttTable $table): void
    {
        app(\App\Services\TableProvisioner::class)->destroy($table);
    }

    /**
     * @param  list<array<string, mixed>>  $out
     */
    private function walkAssets(string $dir, string $type, array &$out, string $prefix = ''): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep' || $item === '.htaccess') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            $rel = ltrim($prefix.$item, '/');
            if (is_dir($path)) {
                $this->walkAssets($path, $type, $out, $rel.'/');

                continue;
            }
            if (! is_file($path)) {
                continue;
            }
            $mime = @mime_content_type($path) ?: 'application/octet-stream';
            $out[] = [
                'type' => $type,
                'name' => $item,
                'relative' => $type.'/'.$rel,
                'size' => (int) filesize($path),
                'mtime' => (int) filemtime($path),
                'mime' => $mime,
                'isImage' => str_starts_with($mime, 'image/'),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path, array $default): array
    {
        if (! File::isFile($path)) {
            return $default;
        }
        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJsonl(string $path): array
    {
        if (! File::isFile($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $types
     */
    private function countEventsSince(array $events, array $types, int $since): int
    {
        $n = 0;
        foreach ($events as $event) {
            if ((int) ($event['ts'] ?? 0) < $since) {
                continue;
            }
            if (in_array((string) ($event['type'] ?? ''), $types, true)) {
                $n++;
            }
        }

        return $n;
    }
}
