<?php
/**
 * Read-only shared graphics. One folder, served at /vtt/global/{map|tokens|backgrounds}/.
 * Tables never copy these files and upload quota does not count them.
 *
 * PHP 7.4 compatible (copied into each table).
 */

function vttGlobalAssetTypes()
{
    return ['map', 'tokens', 'backgrounds'];
}

function vttGlobalImageExtensions()
{
    return ['png', 'jpg', 'jpeg', 'gif', 'webp'];
}

function vttGlobalAssetsEnabled(array $env)
{
    $raw = strtolower(trim((string) (isset($env['VTT_GLOBAL_ASSETS']) ? $env['VTT_GLOBAL_ASSETS'] : '')));
    return $raw === '1' || $raw === 'true' || $raw === 'yes';
}

function vttGlobalDevFallbackPath($projectRoot)
{
    if (!is_string($projectRoot) || $projectRoot === '') {
        return '';
    }
    return rtrim($projectRoot, '/\\')
        . DIRECTORY_SEPARATOR . 'table-manager'
        . DIRECTORY_SEPARATOR . 'public'
        . DIRECTORY_SEPARATOR . 'vtt'
        . DIRECTORY_SEPARATOR . 'global';
}

function vttRealDirectory($path)
{
    if (!is_string($path)) {
        return null;
    }
    $path = trim($path);
    if ($path === '') {
        return null;
    }
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        return null;
    }
    return $real;
}

/**
 * Picker root follows the flag. An explicit missing path does not fall through
 * to the dev sibling. The stream root still resolves so dev can serve files
 * that are already placed on the map.
 */
function vttConfigureGlobalAssets(array $env, $projectRoot = null)
{
    $enabled = vttGlobalAssetsEnabled($env);
    $GLOBALS['VTT_GLOBAL_ASSETS_ENABLED'] = $enabled;

    $configuredRaw = trim((string) (isset($env['VTT_GLOBAL_ASSETS_PATH']) ? $env['VTT_GLOBAL_ASSETS_PATH'] : ''));
    if ($configuredRaw !== '') {
        $configured = vttRealDirectory($configuredRaw);
        $GLOBALS['VTT_GLOBAL_STREAM_ROOT'] = $configured;
        $GLOBALS['VTT_GLOBAL_ASSETS_ROOT'] = ($enabled && $configured !== null) ? $configured : null;
        return;
    }

    $fallback = vttRealDirectory(vttGlobalDevFallbackPath($projectRoot));
    $GLOBALS['VTT_GLOBAL_STREAM_ROOT'] = $fallback;
    $GLOBALS['VTT_GLOBAL_ASSETS_ROOT'] = ($enabled && $fallback !== null) ? $fallback : null;
}

function vttResetGlobalAssets()
{
    unset(
        $GLOBALS['VTT_GLOBAL_ASSETS_ENABLED'],
        $GLOBALS['VTT_GLOBAL_ASSETS_ROOT'],
        $GLOBALS['VTT_GLOBAL_STREAM_ROOT']
    );
}

function vttGlobalAssetsFlag()
{
    return !empty($GLOBALS['VTT_GLOBAL_ASSETS_ENABLED']);
}

function vttNormalizeGlobalRelativePath($path)
{
    if (!is_string($path)) {
        return null;
    }
    $path = str_replace('\\', '/', trim($path));
    $path = trim($path, '/');
    if ($path === '') {
        return '';
    }
    if (strpos($path, '..') !== false || strpos($path, "\0") !== false || strpos($path, '//') !== false) {
        return null;
    }
    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }
    return $path;
}

function vttPathsInside($root, $path)
{
    $root = rtrim(str_replace('\\', '/', (string) $root), '/');
    $path = rtrim(str_replace('\\', '/', (string) $path), '/');
    if (DIRECTORY_SEPARATOR === '\\') {
        $root = strtolower($root);
        $path = strtolower($path);
    }
    return $path === $root || strpos($path, $root . '/') === 0;
}

function vttGlobalResolvedFile($libraryRoot, $type, $relative)
{
    if (!in_array($type, vttGlobalAssetTypes(), true)) {
        return null;
    }
    $relative = vttNormalizeGlobalRelativePath($relative);
    if ($relative === null || $relative === '') {
        return null;
    }
    $typeRoot = realpath($libraryRoot . DIRECTORY_SEPARATOR . $type);
    if ($typeRoot === false || !is_dir($typeRoot) || !vttPathsInside($libraryRoot, $typeRoot)) {
        return null;
    }
    $candidate = $typeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($candidate)) {
        return null;
    }
    $real = realpath($candidate);
    if ($real === false || !vttPathsInside($typeRoot, $real)) {
        return null;
    }
    return $real;
}

function vttResolveGlobalDirectory($libraryRoot, $type, $relative)
{
    if (!in_array($type, vttGlobalAssetTypes(), true)) {
        return null;
    }
    $relative = vttNormalizeGlobalRelativePath($relative);
    if ($relative === null) {
        return null;
    }
    $typeRoot = realpath($libraryRoot . DIRECTORY_SEPARATOR . $type);
    if ($typeRoot === false || !is_dir($typeRoot) || !vttPathsInside($libraryRoot, $typeRoot)) {
        return null;
    }
    if ($relative === '') {
        return $typeRoot;
    }
    $candidate = $typeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $real = realpath($candidate);
    if ($real === false || !is_dir($real) || !vttPathsInside($typeRoot, $real)) {
        return null;
    }
    return $real;
}

function vttGlobalPublicSrc($type, $relative)
{
    return '/vtt/global/' . $type . '/' . str_replace('\\', '/', $relative);
}

function vttGlobalDisplayName($filename)
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    return ucfirst(str_replace(['_', '-'], ' ', $name));
}

function vttGlobalListFolder($type, $rawPath)
{
    $normalized = vttNormalizeGlobalRelativePath(is_string($rawPath) ? $rawPath : '');
    if ($normalized === null) {
        return ['success' => false, 'error' => 'Invalid path'];
    }
    $empty = [
        'success' => true,
        'currentPath' => $normalized,
        'folders' => [],
        'files' => [],
    ];
    $root = isset($GLOBALS['VTT_GLOBAL_ASSETS_ROOT']) ? $GLOBALS['VTT_GLOBAL_ASSETS_ROOT'] : null;
    if (empty($GLOBALS['VTT_GLOBAL_ASSETS_ENABLED']) || !is_string($root) || $root === '') {
        return $empty;
    }
    $dir = vttResolveGlobalDirectory($root, $type, $normalized);
    if ($dir === null) {
        return $empty;
    }

    $entries = scandir($dir);
    if ($entries === false) {
        return $empty;
    }

    $folders = [];
    $files = [];
    $extensions = vttGlobalImageExtensions();
    $typeRoot = realpath($root . DIRECTORY_SEPARATOR . $type);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '' || $entry[0] === '.') {
            continue;
        }
        $entryPath = $dir . DIRECTORY_SEPARATOR . $entry;
        $realEntry = realpath($entryPath);
        if ($realEntry === false || $typeRoot === false || !vttPathsInside($typeRoot, $realEntry)) {
            continue;
        }
        $relativePath = $normalized === '' ? $entry : $normalized . '/' . $entry;
        if (is_dir($realEntry)) {
            $folders[] = ['name' => $entry, 'path' => $relativePath];
            continue;
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $files[] = [
            'id' => $relativePath,
            'name' => vttGlobalDisplayName($entry),
            'src' => vttGlobalPublicSrc($type, $relativePath),
            'global' => true,
        ];
    }
    usort($folders, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    usort($files, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return [
        'success' => true,
        'currentPath' => $normalized,
        'folders' => $folders,
        'files' => $files,
    ];
}

function vttGlobalBackgroundAssets()
{
    $list = vttGlobalListFolder('backgrounds', '');
    if (empty($list['success']) || empty($list['files'])) {
        return [];
    }
    $root = isset($GLOBALS['VTT_GLOBAL_ASSETS_ROOT']) ? $GLOBALS['VTT_GLOBAL_ASSETS_ROOT'] : null;
    $assets = [];
    foreach ($list['files'] as $file) {
        $width = 0;
        $height = 0;
        if (is_string($root)) {
            $full = vttGlobalResolvedFile($root, 'backgrounds', $file['id']);
            if ($full !== null) {
                $info = @getimagesize($full);
                if (is_array($info)) {
                    $width = (int) (isset($info[0]) ? $info[0] : 0);
                    $height = (int) (isset($info[1]) ? $info[1] : 0);
                }
            }
        }
        $assets[] = [
            'id' => $file['id'],
            'filename' => basename(str_replace('\\', '/', $file['id'])),
            'name' => $file['name'],
            'src' => $file['src'],
            'width' => $width,
            'height' => $height,
            'gridWidth' => (int) floor($width / 64),
            'gridHeight' => (int) floor($height / 64),
            'global' => true,
        ];
    }
    return $assets;
}

function vttGlobalStreamFile($rawPath)
{
    $relative = vttNormalizeGlobalRelativePath(is_string($rawPath) ? $rawPath : '');
    if ($relative === null || $relative === '') {
        return null;
    }
    $slash = strpos($relative, '/');
    $type = $slash === false ? $relative : substr($relative, 0, $slash);
    $rest = $slash === false ? '' : substr($relative, $slash + 1);
    $root = isset($GLOBALS['VTT_GLOBAL_STREAM_ROOT']) ? $GLOBALS['VTT_GLOBAL_STREAM_ROOT'] : null;
    if (!is_string($root) || $root === '') {
        return null;
    }
    return vttGlobalResolvedFile($root, $type, $rest);
}

function vttGlobalMime($path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return 'image/jpeg';
    }
    if ($ext === 'gif') {
        return 'image/gif';
    }
    if ($ext === 'webp') {
        return 'image/webp';
    }
    return 'image/png';
}

function vttGlobalEmitRequestedFile($rawPath)
{
    $full = vttGlobalStreamFile($rawPath);
    if ($full === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Not found']);
        return;
    }
    header('Content-Type: ' . vttGlobalMime($full));
    header('Content-Length: ' . (string) filesize($full));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($full);
}
