<?php
/**
 * Per-table quota for GM-uploaded assets (tokens, maps, backgrounds, templates, PDFs).
 * Default: 50 MB. 0 = unlimited. Table Manager writes VTT_TABLE_UPLOAD_QUOTA_MB into each table .env.
 */

const VTT_DEFAULT_UPLOAD_QUOTA_BYTES = 52428800; // 50 MB

function vttAssetBackendRoot(): string
{
    if (!empty($GLOBALS['VTT_ASSET_BACKEND_ROOT']) && is_string($GLOBALS['VTT_ASSET_BACKEND_ROOT'])) {
        return rtrim($GLOBALS['VTT_ASSET_BACKEND_ROOT'], '/\\');
    }

    return dirname(__DIR__);
}

function vttSetAssetBackendRoot(?string $dir): void
{
    if ($dir === null || $dir === '') {
        unset($GLOBALS['VTT_ASSET_BACKEND_ROOT']);

        return;
    }
    $GLOBALS['VTT_ASSET_BACKEND_ROOT'] = rtrim($dir, '/\\');
}

function vttSetUploadQuotaBytes(int $bytes): void
{
    $GLOBALS['VTT_UPLOAD_QUOTA_BYTES'] = max(0, $bytes);
}

function vttParseQuotaFromEnv(array $env): int
{
    if (isset($env['VTT_TABLE_UPLOAD_QUOTA_BYTES']) && is_numeric($env['VTT_TABLE_UPLOAD_QUOTA_BYTES'])) {
        return max(0, (int) $env['VTT_TABLE_UPLOAD_QUOTA_BYTES']);
    }
    if (isset($env['VTT_TABLE_UPLOAD_QUOTA_MB']) && is_numeric($env['VTT_TABLE_UPLOAD_QUOTA_MB'])) {
        return max(0, (int) round((float) $env['VTT_TABLE_UPLOAD_QUOTA_MB'] * 1048576));
    }

    return VTT_DEFAULT_UPLOAD_QUOTA_BYTES;
}

function vttUploadQuotaBytes(): int
{
    if (isset($GLOBALS['VTT_UPLOAD_QUOTA_BYTES'])) {
        return max(0, (int) $GLOBALS['VTT_UPLOAD_QUOTA_BYTES']);
    }

    return VTT_DEFAULT_UPLOAD_QUOTA_BYTES;
}

function vttAssetDirMap(): array
{
    $root = vttAssetBackendRoot();

    return [
        'token' => $root.'/assets/tokens',
        'map' => $root.'/assets/map',
        'background' => $root.'/assets/backgrounds',
        'template' => $root.'/assets/templates',
        'paper' => $root.'/assets/papers',
    ];
}

function vttDirectorySizeBytes(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $total = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
    } catch (UnexpectedValueException $e) {
        return 0;
    }

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if ($name === '.gitkeep' || $name === '.htaccess') {
            continue;
        }
        $total += (int) $file->getSize();
    }

    return $total;
}

function vttAssetUsageBytes(): int
{
    $total = 0;
    foreach (vttAssetDirMap() as $dir) {
        $total += vttDirectorySizeBytes($dir);
    }

    return $total;
}

function vttStorageSnapshot(): array
{
    $used = vttAssetUsageBytes();
    $limit = vttUploadQuotaBytes();
    $remaining = $limit <= 0 ? PHP_INT_MAX : max(0, $limit - $used);

    return [
        'used' => $used,
        'limit' => $limit,
        'remaining' => $remaining,
    ];
}

function vttWouldExceedQuota(int $incomingBytes, int $replacingBytes = 0): bool
{
    $limit = vttUploadQuotaBytes();
    if ($limit <= 0) {
        return false;
    }
    $incomingBytes = max(0, $incomingBytes);
    $replacingBytes = max(0, $replacingBytes);
    $used = vttAssetUsageBytes();

    return ($used - $replacingBytes + $incomingBytes) > $limit;
}

function vttQuotaExceededPayload(): array
{
    return [
        'success' => false,
        'code' => 'quota_exceeded',
        'error' => 'Table storage quota exceeded',
        'storage' => vttStorageSnapshot(),
    ];
}

function vttRejectIfOverQuota(int $incomingBytes, int $replacingBytes = 0): bool
{
    if (!vttWouldExceedQuota($incomingBytes, $replacingBytes)) {
        return false;
    }
    http_response_code(413);
    echo json_encode(vttQuotaExceededPayload());

    return true;
}

function vttUploadFileSize(array $file): int
{
    $size = (int) ($file['size'] ?? 0);
    if ($size > 0) {
        return $size;
    }
    $tmp = $file['tmp_name'] ?? '';
    if (is_string($tmp) && $tmp !== '' && is_file($tmp)) {
        return (int) filesize($tmp);
    }

    return 0;
}
