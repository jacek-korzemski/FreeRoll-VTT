<?php

function loadDeployEnv($rootDir) {
    $path = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!file_exists($path)) {
        return [];
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, '"\'');
            $env[$key] = $value;
        }
    }

    return $env;
}

function normalizeBasePath($path) {
    $path = trim($path);
    if ($path === '') {
        return '/vtt/room1/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    if (substr($path, -1) !== '/') {
        $path .= '/';
    }
    return $path;
}

function getLoginStrings($lang) {
    $lang = strtolower($lang) === 'pl' ? 'pl' : 'en';

    $strings = [
        'en' => [
            'loginTitle' => 'FreeRoll VTT',
            'loginSubtitle' => 'Enter password to continue',
            'loginPlaceholder' => 'Password...',
            'loginSubmit' => 'Enter game',
            'loginError' => 'Invalid password!',
            'loginGmCheckbox' => "I'm Game Master",
            'logout' => 'Logout',
            'appTitle' => 'FreeRoll VTT',
        ],
        'pl' => [
            'loginTitle' => 'FreeRoll VTT',
            'loginSubtitle' => 'Wprowadz haslo aby kontynuowac',
            'loginPlaceholder' => 'Haslo...',
            'loginSubmit' => 'Wejdz do gry',
            'loginError' => 'Nieprawidlowe haslo!',
            'loginGmCheckbox' => 'Jestem Mistrzem Gry',
            'logout' => 'Wyloguj',
            'appTitle' => 'FreeRoll VTT',
        ],
    ];

    return $strings[$lang];
}

function hexToRgbChannels($hex) {
    if (!is_string($hex)) {
        return null;
    }
    $h = ltrim(trim($hex), '#');
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $h)) {
        return null;
    }
    $n = hexdec($h);

    return (($n >> 16) & 255) . ', ' . (($n >> 8) & 255) . ', ' . ($n & 255);
}

function loadColorThemesCatalog($rootDir) {
    $rootDir = rtrim($rootDir, '/\\');
    $candidates = [
        $rootDir . DIRECTORY_SEPARATOR . 'themes.json',
        $rootDir . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'themes.json',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['themes']) && is_array($data['themes']) && $data['themes'] !== []) {
            return $data;
        }
    }

    return [
        'default' => 'crimson',
        'themes' => [],
    ];
}

function resolveColorTemplate($id, array $catalog) {
    $themes = $catalog['themes'] ?? [];
    $id = is_string($id) ? strtolower(trim($id)) : '';
    if ($id !== '' && isset($themes[$id])) {
        return $id;
    }
    $default = $catalog['default'] ?? 'crimson';
    if (isset($themes[$default])) {
        return $default;
    }
    $keys = array_keys($themes);

    return $keys[0] ?? 'crimson';
}

function colorTemplateCssVars(array $tokens) {
    $parts = [];
    foreach ($tokens as $key => $value) {
        if (!is_string($key) || $key === '' || !is_string($value)) {
            continue;
        }
        $parts[] = '--' . $key . ':' . $value;
        if ($value !== '' && $value[0] === '#') {
            $rgb = hexToRgbChannels($value);
            if ($rgb !== null) {
                $parts[] = '--' . $key . '-rgb:' . $rgb;
            }
        }
    }

    return implode(';', $parts);
}

function colorTemplateHtmlAttributes(array $cfg) {
    $id = htmlspecialchars($cfg['colorTemplate'] ?? 'crimson', ENT_QUOTES, 'UTF-8');
    $style = htmlspecialchars($cfg['colorTemplateStyle'] ?? '', ENT_QUOTES, 'UTF-8');

    return 'data-theme="' . $id . '" style="' . $style . '"';
}

function getDeployConfig($rootDir) {
    $env = loadDeployEnv($rootDir);
    $language = strtolower($env['VTT_LANGUAGE'] ?? 'en') === 'pl' ? 'pl' : 'en';
    $enableL5rRaw = strtolower($env['VTT_ENABLE_L5R'] ?? 'false');
    $catalog = loadColorThemesCatalog($rootDir);
    $colorTemplate = resolveColorTemplate($env['VTT_COLOR_TEMPLATE'] ?? '', $catalog);
    $tokens = $catalog['themes'][$colorTemplate]['tokens'] ?? [];
    if ($tokens === []) {
        $tokens = [
            'color-bg' => '#1a1a2e',
            'color-panel' => '#16213e',
            'color-accent' => '#e94560',
            'color-accent-hover' => '#ff6b6b',
            'color-text-strong' => '#ffffff',
            'color-text-muted' => '#cccccc',
            'color-text-dim' => '#888888',
        ];
    }

    return [
        'password' => $env['VTT_PASSWORD'] ?? '2137',
        'gmPassword' => $env['VTT_GM_PASSWORD'] ?? 'admin',
        'basePath' => normalizeBasePath($env['VTT_BASE_PATH'] ?? '/vtt/room1/'),
        'language' => $language,
        'enableL5r' => $enableL5rRaw === 'true' || $enableL5rRaw === '1',
        'allowedOrigins' => $env['ALLOWED_ORIGINS'] ?? '*',
        'colorTemplate' => $colorTemplate,
        'colorTemplateStyle' => colorTemplateCssVars($tokens),
        'loginStrings' => getLoginStrings($language),
    ];
}

function resolveBackendEnvFile($backendDir) {
    $parentEnv = $backendDir . '/../.env';
    if (file_exists($parentEnv)) {
        return $parentEnv;
    }

    $localEnv = $backendDir . '/.env';
    if (file_exists($localEnv)) {
        return $localEnv;
    }

    return $localEnv;
}

/**
 * Start a PHP session scoped to this table's VTT_BASE_PATH so multiple
 * rooms on the same domain do not share login cookies.
 *
 * @param array{basePath?: string}|string $cfgOrRootDir Deploy config or package root dir
 */
function startVttSession($cfgOrRootDir) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = is_array($cfgOrRootDir) ? $cfgOrRootDir : getDeployConfig($cfgOrRootDir);
    $basePath = normalizeBasePath($cfg['basePath'] ?? '/vtt/room1/');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_name('VTTSESS_' . substr(hash('sha256', $basePath), 0, 12));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $basePath,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
