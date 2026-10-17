<?php

namespace FreeRoll\Ttrpg;

use ZipArchive;

final class AdventurePackageError extends \RuntimeException
{
    /** @var string */
    private $errorCode;

    /** @var int */
    private $status;

    public function __construct(string $message, string $errorCode = 'bad_package', int $status = 400)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}

/**
 * Zip of one adventure: entries, open tabs, and the images those entries reference.
 */
final class AdventurePackage
{
    public const FORMAT = 'freeroll-adventure';
    public const VERSION = 1;
    public const MAX_ZIP_BYTES = 41943040;
    public const MAX_ENTRIES = 500;
    public const MAX_BODY_BYTES = 8000000;
    public const MAX_ZIP_FILES = 300;

    /**
     * @param array{tabIds?: mixed, activeId?: mixed} $tabs
     */
    public static function exportFile(AdventureStore $store, array $tabs): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new AdventurePackageError('Zip support is missing on this server', 'zip_missing', 500);
        }

        $rows = $store->allWithBodies();
        $idToKey = [];
        foreach ($rows as $row) {
            $idToKey[(string) $row['id']] = 'e' . $row['id'];
        }

        $entries = [];
        $htmlForAssets = '';
        foreach ($rows as $index => $row) {
            $html = self::rewriteExtensionMismatches(
                AdventureHtml::remapEntryIds((string) $row['bodyHtml'], $idToKey)
            );
            $entries[] = [
                'key' => $idToKey[(string) $row['id']],
                'title' => (string) $row['title'],
                'sortOrder' => $index,
                'bodyHtml' => $html,
            ];
            $htmlForAssets .= $html;
        }

        $tabKeys = [];
        $tabIds = isset($tabs['tabIds']) && is_array($tabs['tabIds']) ? $tabs['tabIds'] : [];
        foreach ($tabIds as $tabId) {
            $token = (string) (int) $tabId;
            if (isset($idToKey[$token]) && !in_array($idToKey[$token], $tabKeys, true)) {
                $tabKeys[] = $idToKey[$token];
            }
        }
        $activeToken = (string) (int) ($tabs['activeId'] ?? 0);
        $activeKey = $idToKey[$activeToken] ?? null;

        $assets = self::assetsFromHtml($htmlForAssets);
        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => time(),
            'entries' => $entries,
            'tabs' => [
                'tabKeys' => $tabKeys,
                'activeKey' => $activeKey,
            ],
            'assets' => $assets['manifest'],
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new AdventurePackageError('Could not encode the adventure', 'bad_package', 500);
        }

        $path = tempnam(sys_get_temp_dir(), 'adv');
        if ($path === false) {
            throw new AdventurePackageError('Could not create the archive', 'bad_package', 500);
        }
        $zipPath = $path . '.zip';
        @unlink($path);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new AdventurePackageError('Could not create the archive', 'bad_package', 500);
        }
        $zip->addFromString('manifest.json', $json);
        foreach ($assets['files'] as $item) {
            $bytes = @file_get_contents($item['full']);
            if ($bytes === false) {
                $zip->close();
                @unlink($zipPath);
                throw new AdventurePackageError('Could not read an image used in the adventure', 'bad_package', 500);
            }
            $zip->addFromString($item['zipPath'], $bytes);
        }
        $zip->close();

        $size = @filesize($zipPath);
        if ($size === false || $size > self::MAX_ZIP_BYTES) {
            @unlink($zipPath);
            throw new AdventurePackageError('Adventure archive is too large', 'bad_package', 413);
        }

        return $zipPath;
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, tabs: array{tabIds: int[], activeId: int|null}}
     */
    public static function importFile(AdventureStore $store, string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new AdventurePackageError('Zip support is missing on this server', 'zip_missing', 500);
        }
        $size = @filesize($zipPath);
        if ($size === false || $size < 1 || $size > self::MAX_ZIP_BYTES) {
            throw new AdventurePackageError('Adventure archive is too large', 'bad_package', 413);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new AdventurePackageError('Could not open the archive', 'bad_package');
        }

        try {
            $unpacked = 0;
            $count = $zip->numFiles;
            if ($count < 1 || $count > self::MAX_ZIP_FILES) {
                throw new AdventurePackageError('Adventure archive is not valid', 'bad_package');
            }
            for ($i = 0; $i < $count; $i++) {
                $stat = $zip->statIndex($i);
                $unpacked += (int) ($stat['size'] ?? 0);
                if ($unpacked > self::MAX_ZIP_BYTES) {
                    throw new AdventurePackageError('Adventure archive is too large', 'bad_package', 413);
                }
            }

            $raw = $zip->getFromName('manifest.json');
            if (!is_string($raw) || $raw === '') {
                throw new AdventurePackageError('Adventure archive has no manifest', 'bad_package');
            }
            $manifest = json_decode($raw, true);
            if (!is_array($manifest)) {
                throw new AdventurePackageError('Adventure manifest is not valid', 'bad_package');
            }

            $entries = self::normalizeEntries($manifest);
            $planned = self::planAssetWrites($zip, $manifest, $entries);
            self::assertQuota($planned['incoming']);
            $written = self::writePlanned($planned['writes']);
        } catch (AdventurePackageError $e) {
            $zip->close();
            throw $e;
        } catch (\Throwable $e) {
            $zip->close();
            throw new AdventurePackageError('Could not read the adventure archive', 'bad_package');
        }

        $zip->close();

        foreach ($entries as $index => $entry) {
            $entries[$index]['bodyHtml'] = self::replaceSrcs($entry['bodyHtml'], $planned['renames']);
        }

        try {
            return self::apply($store, [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'entries' => $entries,
                'tabs' => isset($manifest['tabs']) && is_array($manifest['tabs']) ? $manifest['tabs'] : [],
            ]);
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            if ($e instanceof AdventurePackageError) {
                throw $e;
            }
            throw new AdventurePackageError('Could not save the adventure', 'bad_package', 500);
        }
    }

    /**
     * Install a manifest-shaped package. Images must already be on disk.
     *
     * @param array<string, mixed> $package
     * @return array{entries: array<int, array<string, mixed>>, tabs: array{tabIds: int[], activeId: int|null}}
     */
    public static function apply(AdventureStore $store, array $package): array
    {
        $entries = self::normalizeEntries($package);
        $stored = $store->replaceMapped($entries);
        $idByKey = $stored['idByKey'];
        $tabs = isset($package['tabs']) && is_array($package['tabs']) ? $package['tabs'] : [];
        $tabKeys = isset($tabs['tabKeys']) && is_array($tabs['tabKeys']) ? $tabs['tabKeys'] : [];
        $tabIds = [];
        foreach ($tabKeys as $key) {
            $key = (string) $key;
            if (isset($idByKey[$key]) && !in_array($idByKey[$key], $tabIds, true)) {
                $tabIds[] = $idByKey[$key];
            }
        }
        $activeKey = isset($tabs['activeKey']) ? (string) $tabs['activeKey'] : '';
        $activeId = $activeKey !== '' && isset($idByKey[$activeKey]) ? $idByKey[$activeKey] : null;
        if ($activeId !== null && !in_array($activeId, $tabIds, true)) {
            $tabIds[] = $activeId;
        }
        if ($tabIds === [] && isset($stored['entries'][0]['id'])) {
            $tabIds[] = (int) $stored['entries'][0]['id'];
            $activeId = (int) $stored['entries'][0]['id'];
        }

        return [
            'entries' => $stored['entries'],
            'tabs' => [
                'tabIds' => $tabIds,
                'activeId' => $activeId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $package
     * @return array<int, array{key: string, title: string, bodyHtml: string, sortOrder: int}>
     */
    private static function normalizeEntries(array $package): array
    {
        if (($package['format'] ?? self::FORMAT) !== self::FORMAT) {
            throw new AdventurePackageError('This file is not an adventure package', 'bad_package');
        }
        $version = (int) ($package['version'] ?? 0);
        if ($version !== self::VERSION) {
            throw new AdventurePackageError('This adventure package is not supported', 'bad_package');
        }
        $raw = $package['entries'] ?? null;
        if (!is_array($raw) || $raw === []) {
            throw new AdventurePackageError('The adventure package has no entries', 'bad_package');
        }
        if (count($raw) > self::MAX_ENTRIES) {
            throw new AdventurePackageError('The adventure package has too many entries', 'bad_package');
        }

        $indexed = [];
        $seen = [];
        $bodyBytes = 0;
        foreach ($raw as $index => $entry) {
            if (!is_array($entry)) {
                throw new AdventurePackageError('An adventure entry is not valid', 'bad_package');
            }
            $key = (string) ($entry['key'] ?? '');
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $key)) {
                throw new AdventurePackageError('An adventure entry key is not valid', 'bad_package');
            }
            if (isset($seen[$key])) {
                throw new AdventurePackageError('Adventure entry keys must be unique', 'bad_package');
            }
            $seen[$key] = true;
            $body = (string) ($entry['bodyHtml'] ?? '');
            $bodyBytes += strlen($body);
            if ($bodyBytes > self::MAX_BODY_BYTES) {
                throw new AdventurePackageError('The adventure package is too large', 'bad_package', 413);
            }
            $indexed[] = [
                'i' => (int) $index,
                'e' => [
                    'key' => $key,
                    'title' => (string) ($entry['title'] ?? ''),
                    'bodyHtml' => $body,
                    'sortOrder' => (int) ($entry['sortOrder'] ?? $index),
                ],
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            if ($a['e']['sortOrder'] === $b['e']['sortOrder']) {
                return $a['i'] <=> $b['i'];
            }
            return $a['e']['sortOrder'] <=> $b['e']['sortOrder'];
        });

        $entries = [];
        foreach ($indexed as $row) {
            $entries[] = $row['e'];
        }
        return $entries;
    }

    /**
     * @return array{manifest: array<int, array{zipPath: string, src: string, assetType: string}>, files: array<int, array{zipPath: string, full: string}>}
     */
    private static function assetsFromHtml(string $html): array
    {
        $manifest = [];
        $files = [];
        foreach (self::srcsInHtml($html) as $src) {
            $full = self::fullPath($src);
            if ($full === null || !is_file($full)) {
                continue;
            }
            $zipPath = substr($src, strlen('backend/'));
            $manifest[] = [
                'zipPath' => $zipPath,
                'src' => $src,
                'assetType' => self::assetType($src),
            ];
            $files[] = ['zipPath' => $zipPath, 'full' => $full];
        }
        return ['manifest' => $manifest, 'files' => $files];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, array{key: string, title: string, bodyHtml: string, sortOrder: int}> $entries
     * @return array{incoming: int, writes: array<int, array{dest: string, bytes: string}>, renames: array<string, string>}
     */
    private static function planAssetWrites(ZipArchive $zip, array $manifest, array $entries): array
    {
        $wanted = [];
        $listed = isset($manifest['assets']) && is_array($manifest['assets']) ? $manifest['assets'] : [];
        foreach ($listed as $asset) {
            if (!is_array($asset)) {
                throw new AdventurePackageError('An adventure image is not valid', 'bad_package');
            }
            $src = self::canonicalSrc((string) ($asset['src'] ?? ''));
            if ($src === null) {
                throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
            }
            $zipPath = str_replace('\\', '/', (string) ($asset['zipPath'] ?? ''));
            if ($zipPath !== substr($src, strlen('backend/'))) {
                throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
            }
            $wanted[$src] = $zipPath;
        }

        $html = '';
        foreach ($entries as $entry) {
            $html .= $entry['bodyHtml'];
        }
        foreach (self::srcsInHtml($html) as $src) {
            $zipPath = substr($src, strlen('backend/'));
            if (!isset($wanted[$src]) && $zip->locateName($zipPath) !== false) {
                $wanted[$src] = $zipPath;
            }
        }

        $writes = [];
        $renames = [];
        $incoming = 0;
        foreach ($wanted as $src => $zipPath) {
            $bytes = $zip->getFromName($zipPath);
            if (!is_string($bytes) || $bytes === '') {
                throw new AdventurePackageError('An image is missing from the archive', 'bad_package');
            }
            if (strlen($bytes) > self::MAX_ZIP_BYTES) {
                throw new AdventurePackageError('An adventure image is too large', 'bad_package', 413);
            }
            if (!self::isImageBytes($bytes)) {
                throw new AdventurePackageError('An adventure file is not an image', 'bad_package');
            }
            $dest = self::fullPath($src);
            if ($dest === null) {
                throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
            }
            if (is_file($dest) && hash_equals(hash_file('sha256', $dest), hash('sha256', $bytes))) {
                continue;
            }
            if (is_file($dest)) {
                $dest = self::uniqueDest($dest);
                $renames[$src] = self::srcFromFull($dest);
            }
            $incoming += strlen($bytes);
            $writes[] = ['dest' => $dest, 'bytes' => $bytes];
        }

        foreach (self::srcsInHtml($html) as $src) {
            if (isset($wanted[$src])) {
                continue;
            }
            $resolved = self::resolveOnDisk($src);
            if ($resolved !== null && $resolved !== $src && !isset($renames[$src])) {
                $renames[$src] = $resolved;
            }
        }

        return ['incoming' => $incoming, 'writes' => $writes, 'renames' => $renames];
    }

    /**
     * @param array<int, array{dest: string, bytes: string}> $writes
     * @return string[]
     */
    private static function writePlanned(array $writes): array
    {
        $written = [];
        foreach ($writes as $item) {
            $dest = $item['dest'];
            self::ensureDir(dirname($dest));
            if (@file_put_contents($dest, $item['bytes']) === false) {
                foreach ($written as $path) {
                    @unlink($path);
                }
                throw new AdventurePackageError('Could not save an adventure image', 'bad_package', 500);
            }
            $written[] = $dest;
        }
        return $written;
    }

    private static function assertQuota(int $incoming): void
    {
        if ($incoming < 1) {
            return;
        }
        if (function_exists('vttWouldExceedQuota') && vttWouldExceedQuota($incoming)) {
            throw new AdventurePackageError('Table storage quota exceeded', 'quota_exceeded', 413);
        }
    }

    /**
     * @return string[]
     */
    private static function rewriteExtensionMismatches(string $html): string
    {
        $renames = [];
        foreach (self::srcsInHtml($html) as $src) {
            $resolved = self::resolveOnDisk($src);
            if ($resolved !== null && $resolved !== $src) {
                $renames[$src] = $resolved;
            }
        }
        return self::replaceSrcs($html, $renames);
    }

    /**
     * The stored name sometimes disagrees with the file extension.
     * Use the file that is actually on disk.
     */
    private static function resolveOnDisk(string $src): ?string
    {
        $full = self::fullPath($src);
        if ($full === null) {
            return null;
        }
        if (is_file($full)) {
            return $src;
        }
        $info = pathinfo($full);
        $dir = $info['dirname'] ?? '';
        $name = $info['filename'] ?? '';
        if ($dir === '' || $name === '') {
            return null;
        }
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
            $candidate = $dir . '/' . $name . '.' . $ext;
            if (!is_file($candidate)) {
                continue;
            }
            return self::srcFromFull($candidate);
        }
        return null;
    }

    private static function srcsInHtml(string $html): array
    {
        if (!preg_match_all('/\b(?:src|data-src)=(["\'])([^"\']+)\1/i', $html, $matches)) {
            return [];
        }
        $out = [];
        foreach ($matches[2] as $candidate) {
            $src = self::canonicalSrc(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($src !== null && !in_array($src, $out, true)) {
                $out[] = $src;
            }
        }
        return $out;
    }

    private static function canonicalSrc(string $src): ?string
    {
        $src = str_replace('\\', '/', trim($src));
        if ($src === '' || strpos($src, '..') !== false || strpos($src, '//') !== false) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $src)) {
            return null;
        }
        if ($src[0] === '/' || $src[0] === '?') {
            return null;
        }
        if (!preg_match('#^backend/assets/(map|tokens|backgrounds)/#', $src)) {
            return null;
        }
        if (!preg_match('#\.(png|jpe?g|gif|webp)$#i', $src)) {
            return null;
        }
        if (preg_match('#[\x00-\x1F<>"\']#', $src)) {
            return null;
        }
        return $src;
    }

    private static function assetType(string $src): string
    {
        if (strpos($src, '/tokens/') !== false) {
            return 'token';
        }
        if (strpos($src, '/backgrounds/') !== false) {
            return 'background';
        }
        return 'map';
    }

    private static function assetRoot(): string
    {
        if (function_exists('vttAssetBackendRoot')) {
            return rtrim(vttAssetBackendRoot(), '/\\');
        }
        return dirname(__DIR__, 2);
    }

    private static function fullPath(string $src): ?string
    {
        $src = self::canonicalSrc($src);
        if ($src === null) {
            return null;
        }
        $root = str_replace('\\', '/', self::assetRoot());
        $dest = $root . '/' . substr($src, strlen('backend/'));
        $realRoot = realpath($root);
        if ($realRoot === false) {
            return null;
        }
        $parent = dirname($dest);
        if (is_dir($parent)) {
            $realParent = realpath($parent);
            if ($realParent === false || !self::isInside($realParent, $realRoot)) {
                return null;
            }
        }
        return $dest;
    }

    private static function srcFromFull(string $full): string
    {
        $root = str_replace('\\', '/', self::assetRoot());
        $full = str_replace('\\', '/', $full);
        $prefix = rtrim($root, '/') . '/';
        if (strpos($full, $prefix) !== 0) {
            throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
        }
        return 'backend/' . substr($full, strlen($prefix));
    }

    private static function uniqueDest(string $dest): string
    {
        $info = pathinfo($dest);
        $dir = $info['dirname'] ?? '.';
        $base = $info['filename'] ?? 'image';
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $i = 1;
        do {
            $candidate = $dir . '/' . $base . '-' . $i . $ext;
            $i++;
        } while (is_file($candidate) && $i < 1000);
        return $candidate;
    }

    private static function ensureDir(string $dir): void
    {
        $root = realpath(self::assetRoot());
        if ($root === false) {
            throw new AdventurePackageError('Could not save an adventure image', 'bad_package', 500);
        }
        if (is_dir($dir)) {
            $real = realpath($dir);
            if ($real === false || !self::isInside($real, $root)) {
                throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
            }
            return;
        }
        $parent = dirname($dir);
        if (!is_dir($parent)) {
            self::ensureDir($parent);
        }
        if (!@mkdir($dir, 0755) && !is_dir($dir)) {
            throw new AdventurePackageError('Could not save an adventure image', 'bad_package', 500);
        }
        $real = realpath($dir);
        if ($real === false || !self::isInside($real, $root)) {
            throw new AdventurePackageError('An adventure image path is not valid', 'bad_package');
        }
    }

    private static function isInside(string $path, string $root): bool
    {
        $path = strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        $root = strtolower(rtrim(str_replace('\\', '/', $root), '/'));
        return $path === $root || strpos($path, $root . '/') === 0;
    }

    private static function isImageBytes(string $bytes): bool
    {
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info)) {
            return false;
        }
        $mime = (string) ($info['mime'] ?? '');
        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    /**
     * @param array<string, string> $renames
     */
    private static function replaceSrcs(string $html, array $renames): string
    {
        uksort($renames, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });
        foreach ($renames as $old => $new) {
            $html = str_replace($old, $new, $html);
        }
        return $html;
    }
}
