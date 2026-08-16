<?php
// Treat all string operations as UTF-8 by default. Prevents `substr`-style
// byte truncation from cutting Polish/UTF-8 characters in half.
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Optional Composer autoload (TTRPG Manager integration layer).
$ttrpgAutoload = __DIR__ . '/vendor/autoload.php';
$ttrpgReady = is_file($ttrpgAutoload);
if ($ttrpgReady) {
    require_once $ttrpgAutoload;
}

/**
 * Public TTRPG Manager integration snapshot (never includes API key).
 *
 * @return array{configured: bool, baseUrl: ?string, campaignId: ?int}
 */
function ttrpgPublicStatus(): array {
    global $ttrpgReady;
    if (!$ttrpgReady || !class_exists(\FreeRoll\Ttrpg\Actions::class)) {
        return ['configured' => false, 'baseUrl' => null, 'campaignId' => null];
    }
    try {
        return \FreeRoll\Ttrpg\Actions::publicStatus();
    } catch (Throwable $e) {
        return ['configured' => false, 'baseUrl' => null, 'campaignId' => null];
    }
}

function ttrpgRequireReady(): bool {
    global $ttrpgReady;
    if ($ttrpgReady && class_exists(\FreeRoll\Ttrpg\Actions::class)) {
        return true;
    }
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'TTRPG integration unavailable. Run composer install in backend/.',
    ]);
    return false;
}

/** Bump state.json version so clients poll a refresh (ttrpgManager is not stored in state). */
function ttrpgBumpStateVersion(): int {
    $state = getState();
    $state = saveState($state);
    return (int) ($state['version'] ?? 0);
}

// ============================================
// Sprawdzanie autoryzacji
// ============================================
function isDevMode() {
    // Tryb deweloperski - sprawdź czy origin to localhost (Vite dev server)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    
    // Sprawdź czy to localhost na różnych portach (dev mode)
    // Vite dev server zwykle działa na porcie 5173
    if (strpos($origin, 'http://localhost:') === 0 || 
        strpos($origin, 'http://127.0.0.1:') === 0 ||
        strpos($host, 'localhost') !== false ||
        strpos($host, '127.0.0.1') !== false ||
        strpos($serverName, 'localhost') !== false ||
        strpos($serverName, '127.0.0.1') !== false) {
        return true;
    }
    
    return false;
}

function isAuthenticated() {
    // W trybie deweloperskim zawsze zwracaj true (pomijamy autoryzację)
    if (isDevMode()) {
        return true;
    }
    return isset($_SESSION['vtt_authenticated']) && $_SESSION['vtt_authenticated'] === true;
}

function isGameMaster() {
    // Bypass tylko z localhost + nagłówek X-Dev-GM (localStorage na froncie → nagłówek w main.jsx)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (isset($_SERVER['HTTP_X_DEV_GM']) && $_SERVER['HTTP_X_DEV_GM'] === '1'
        && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin)) {
        return true;
    }
    // W trybie deweloperskim (origin/localhost) sprawdź parametr URL lub cookie
    if (isDevMode()) {
        if (isset($_GET['gm']) && $_GET['gm'] === '1') {
            return true;
        }
        if (isset($_COOKIE['dev_gm']) && $_COOKIE['dev_gm'] === '1') {
            return true;
        }
        return false;
    }
    // W trybie produkcyjnym sprawdź sesję
    return isAuthenticated() && isset($_SESSION['vtt_is_gm']) && $_SESSION['vtt_is_gm'] === true;
}

define('ROLLS_HISTORY_MAX', 20);
define('ROLL_SNACKBAR_MAX_AGE_SEC', 600);

function loadRolls($rollsFile) {
    if (!file_exists($rollsFile)) {
        return [];
    }
    $rolls = json_decode(file_get_contents($rollsFile), true);
    if (!is_array($rolls)) {
        return [];
    }
    usort($rolls, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
    return array_slice($rolls, 0, ROLLS_HISTORY_MAX);
}

function saveRolls($rollsFile, array $rolls) {
    $rolls = array_slice($rolls, 0, ROLLS_HISTORY_MAX);
    file_put_contents($rollsFile, json_encode($rolls, JSON_PRETTY_PRINT));
}

function annotateRollsForSnackbar(array $rolls): array {
    $nowMs = (int) round(microtime(true) * 1000);
    $cutoffMs = $nowMs - (ROLL_SNACKBAR_MAX_AGE_SEC * 1000);
    return array_map(function ($roll) use ($cutoffMs) {
        $ts = (int) ($roll['timestamp'] ?? 0);
        $roll['snackbar'] = $ts >= $cutoffMs;
        return $roll;
    }, $rolls);
}

// ============================================
// Ładowanie konfiguracji z .env
// ============================================
function loadEnv($path) {
    if (!file_exists($path)) return [];
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
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

require_once __DIR__ . '/../deploy-env.php';

$envFile = resolveBackendEnvFile(__DIR__);
$env = loadEnv($envFile);
startVttSession(dirname(__DIR__));

// ============================================
// CORS
// ============================================
header('Content-Type: application/json');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOriginsStr = $env['ALLOWED_ORIGINS'] ?? 'http://localhost:5173';
$allowedOrigins = array_map('trim', explode(',', $allowedOriginsStr));

if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Dev-GM');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ============================================
// Helpers
// ============================================

$dataFile = __DIR__ . '/data/state.json';

if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

function generateId() {
    return uniqid('', true);
}

function createEmptyScene($name = 'New Scene') {
    return [
        'id' => 'scene_' . generateId(),
        'name' => $name,
        'background' => null,
        'fogOfWar' => ['enabled' => false, 'data' => null],
        'mapElements' => [],
        'tokens' => []
    ];
}

function getState() {
    global $dataFile;
    
    if (!file_exists($dataFile)) {
        $defaultScene = createEmptyScene('Scene 1');
        $initialState = [
            'activeSceneId' => $defaultScene['id'],
            'scenes' => [$defaultScene],
            'counters' => [],
            'lastUpdate' => time(),
            'version' => 0
        ];
        file_put_contents($dataFile, json_encode($initialState, JSON_PRETTY_PRINT));
        return $initialState;
    }
    
    $content = file_get_contents($dataFile);
    $state = json_decode($content, true);
    
    // Migracja ze starego formatu (bez scen)
    if (!isset($state['scenes'])) {
        $scene = createEmptyScene('Scene 1');
        $scene['background'] = $state['background'] ?? null;
        $scene['fogOfWar'] = $state['fogOfWar'] ?? ['enabled' => false, 'data' => null];
        $scene['mapElements'] = $state['mapElements'] ?? [];
        $scene['tokens'] = $state['tokens'] ?? [];
        
        $state = [
            'activeSceneId' => $scene['id'],
            'scenes' => [$scene],
            'lastUpdate' => $state['lastUpdate'] ?? time(),
            'version' => $state['version'] ?? 0
        ];
    }
    
    if (!isset($state['counters']) || !is_array($state['counters'])) {
        $state['counters'] = [];
    }
    
    return $state;
}

function sanitizeClientId($id) {
    if (!is_string($id)) {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9-]{8,64}$/', $id)) {
        return '';
    }
    return $id;
}

function sanitizeCounterId($id) {
    if (!is_string($id)) {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9-]{1,48}$/', $id)) {
        return '';
    }
    return $id;
}

function canEditCounter($counter, $clientId) {
    if (isGameMaster()) {
        return true;
    }
    return isset($counter['ownerId']) && $counter['ownerId'] === $clientId && $clientId !== '';
}

function findCounterIndex(&$state, $counterId) {
    if (!isset($state['counters']) || !is_array($state['counters'])) {
        return null;
    }
    foreach ($state['counters'] as $idx => $c) {
        if (($c['id'] ?? '') === $counterId) {
            return $idx;
        }
    }
    return null;
}

const COUNTER_TITLE_MAX = 120;
const COUNTER_NOTES_MAX = 2000;
const COUNTER_DURATION_MAX_SEC = 604800;

function saveState($state) {
    global $dataFile;
    $state['lastUpdate'] = time();
    $state['version'] = ($state['version'] ?? 0) + 1;
    file_put_contents($dataFile, json_encode($state, JSON_PRETTY_PRINT));
    return $state;
}

function getActiveScene(&$state) {
    foreach ($state['scenes'] as &$scene) {
        if ($scene['id'] === $state['activeSceneId']) {
            return $scene;
        }
    }
    // Fallback - pierwsza scena
    if (!empty($state['scenes'])) {
        $state['activeSceneId'] = $state['scenes'][0]['id'];
        return $state['scenes'][0];
    }
    return null;
}

function updateActiveScene(&$state, $updatedScene) {
    foreach ($state['scenes'] as $idx => $scene) {
        if ($scene['id'] === $state['activeSceneId']) {
            $state['scenes'][$idx] = $updatedScene;
            return;
        }
    }
}

function getSceneById(&$state, $sceneId) {
    foreach ($state['scenes'] as &$scene) {
        if ($scene['id'] === $sceneId) {
            return $scene;
        }
    }
    return null;
}

function getAssetBaseDirMap() {
    return [
        'token' => __DIR__ . '/assets/tokens',
        'map' => __DIR__ . '/assets/map',
        'background' => __DIR__ . '/assets/backgrounds',
        'template' => __DIR__ . '/assets/templates',
        'paper' => __DIR__ . '/assets/papers',
    ];
}

function tokenMapAssetRef($item) {
    if (!is_array($item)) {
        return '';
    }
    if (!empty($item['assetId']) && is_string($item['assetId'])) {
        return trim(str_replace('\\', '/', $item['assetId']), '/');
    }
    if (!empty($item['src']) && is_string($item['src'])) {
        $src = str_replace('\\', '/', $item['src']);
        if (preg_match('#backend/assets/(?:tokens|map)/(.+)$#i', $src, $m)) {
            return trim($m[1], '/');
        }
    }
    return '';
}

function collectAssetUsageFromState($state) {
    $usage = [
        'token' => [],
        'map' => [],
        'background' => [],
    ];

    foreach ($state['scenes'] ?? [] as $scene) {
        $sceneName = is_string($scene['name'] ?? null) ? $scene['name'] : 'Scene';

        if (!empty($scene['background']['src']) && is_string($scene['background']['src'])) {
            $filename = basename(str_replace('\\', '/', $scene['background']['src']));
            if ($filename !== '') {
                if (!isset($usage['background'][$filename])) {
                    $usage['background'][$filename] = [];
                }
                if (!in_array($sceneName, $usage['background'][$filename], true)) {
                    $usage['background'][$filename][] = $sceneName;
                }
            }
        }

        foreach ($scene['tokens'] ?? [] as $token) {
            $ref = tokenMapAssetRef($token);
            if ($ref === '') {
                continue;
            }
            if (!isset($usage['token'][$ref])) {
                $usage['token'][$ref] = [];
            }
            if (!in_array($sceneName, $usage['token'][$ref], true)) {
                $usage['token'][$ref][] = $sceneName;
            }
        }

        foreach ($scene['mapElements'] ?? [] as $element) {
            $ref = tokenMapAssetRef($element);
            if ($ref === '') {
                continue;
            }
            if (!isset($usage['map'][$ref])) {
                $usage['map'][$ref] = [];
            }
            if (!in_array($sceneName, $usage['map'][$ref], true)) {
                $usage['map'][$ref][] = $sceneName;
            }
        }
    }

    return $usage;
}

function normalizeDeleteAssetId($type, $id) {
    if (!is_string($id)) {
        return '';
    }
    $id = trim(str_replace('\\', '/', $id), '/');
    if ($id === '' || strpos($id, '..') !== false) {
        return '';
    }
    if ($type === 'background' || $type === 'paper' || $type === 'template') {
        return basename($id);
    }
    return $id;
}

function isAssetInUse($type, $id, $usage) {
    if (!in_array($type, ['token', 'map', 'background'], true)) {
        return false;
    }
    $normalized = normalizeDeleteAssetId($type, $id);
    if ($normalized === '') {
        return false;
    }
    return !empty($usage[$type][$normalized]);
}

function getAssetInUseScenes($type, $id, $usage) {
    $normalized = normalizeDeleteAssetId($type, $id);
    if ($normalized === '' || !isset($usage[$type][$normalized])) {
        return [];
    }
    return $usage[$type][$normalized];
}

function resolveAssetFilePath($type, $id) {
    $baseDirMap = getAssetBaseDirMap();
    if (!isset($baseDirMap[$type])) {
        return null;
    }

    $baseDir = realpath($baseDirMap[$type]);
    if ($baseDir === false) {
        return null;
    }

    $normalized = normalizeDeleteAssetId($type, $id);
    if ($normalized === '') {
        return null;
    }

    if ($type === 'template' && !preg_match('/\.html?$/i', $normalized)) {
        return null;
    }
    if ($type === 'paper' && !preg_match('/\.pdf$/i', $normalized)) {
        return null;
    }

    if (in_array($type, ['background', 'paper', 'template'], true)) {
        $candidate = $baseDir . DIRECTORY_SEPARATOR . $normalized;
    } else {
        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    if (!is_file($candidate)) {
        return null;
    }

    $resolved = realpath($candidate);
    if ($resolved === false) {
        return null;
    }

    $basePrefix = $baseDir . DIRECTORY_SEPARATOR;
    if ($resolved !== $baseDir && strpos($resolved, $basePrefix) !== 0) {
        return null;
    }

    return $resolved;
}

/**
 * Ensure vtt-template meta is present in HTML (for editor/clone distinction).
 * @param string $html
 * @param string|null $source e.g. 'custom-clone' for cloned custom templates
 * @return string
 */
function injectTemplateMeta($html, $source = null) {
    $content = 'editor;v=1';
    if ($source !== null && $source !== '') {
        $content .= ';source=' . preg_replace('/[^a-z0-9_-]/i', '', $source);
    }
    $metaTag = '<meta name="vtt-template" content="' . htmlspecialchars($content) . '">';
    if (preg_match('/<meta\s+name=["\']vtt-template["\'][^>]*>/i', $html)) {
        return preg_replace('/<meta\s+name=["\']vtt-template["\'][^>]*>/i', $metaTag, $html, 1);
    }
    if (preg_match('/<head([^>]*)>/i', $html)) {
        return preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n  " . $metaTag, $html, 1);
    }
    if (preg_match('/<body([^>]*)>/i', $html)) {
        return preg_replace('/<body([^>]*)>/i', $metaTag . "\n<body$1>", $html, 1);
    }
    return $metaTag . "\n" . $html;
}

// ============================================
// API
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Sprawdź autoryzację dla wszystkich endpointów oprócz 'auth'
    $isDevBypass = (
        isset($_SERVER['HTTP_X_DEV_GM']) && $_SERVER['HTTP_X_DEV_GM'] === '1'
        && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $_SERVER['HTTP_ORIGIN'] ?? '')
    );
    if ($action !== 'auth' && !isAuthenticated() && !$isDevBypass) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'auth':
                    // Endpoint do sprawdzania roli użytkownika
                    // W trybie dev zawsze zwracaj authenticated=true
                    $authenticated = isDevMode() ? true : isAuthenticated();
                    $isGM = isGameMaster();
                    
                    echo json_encode([
                        'success' => true,
                        'authenticated' => $authenticated,
                        'isGameMaster' => $isGM
                    ]);
                    break;

                case 'state':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'activeSceneId' => $state['activeSceneId'],
                            'scenes' => array_map(function($s) {
                                return ['id' => $s['id'], 'name' => $s['name']];
                            }, $state['scenes']),
                            'scene' => $activeScene,
                            'counters' => $state['counters'] ?? [],
                            'version' => $state['version'],
                            'serverNow' => time(),
                            'ttrpgManager' => ttrpgPublicStatus(),
                        ]
                    ]);
                    break;

                case 'ping':
                    $state = getState();
                    $ping = $state['ping'] ?? null;
                    echo json_encode(['success' => true, 'ping' => $ping]);
                    break;

                case 'check':
                    $clientVersion = intval($_GET['version'] ?? 0);
                    $state = getState();
                    $ttrpgManager = ttrpgPublicStatus();
                    
                    if ($state['version'] > $clientVersion) {
                        $activeScene = getActiveScene($state);
                        echo json_encode([
                            'success' => true,
                            'hasChanges' => true,
                            'data' => [
                                'activeSceneId' => $state['activeSceneId'],
                                'scenes' => array_map(function($s) {
                                    return ['id' => $s['id'], 'name' => $s['name']];
                                }, $state['scenes']),
                                'scene' => $activeScene,
                                'counters' => $state['counters'] ?? [],
                                'version' => $state['version'],
                                'serverNow' => time(),
                                'ttrpgManager' => $ttrpgManager,
                            ]
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'hasChanges' => false,
                            'version' => $state['version'],
                            'serverNow' => time(),
                            'ttrpgManager' => $ttrpgManager,
                        ]);
                    }
                    break;

                case 'ttrpg-status':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    echo json_encode(\FreeRoll\Ttrpg\Actions::status());
                    break;

                case 'list-map':
                    // Lista elementów mapy z nawigacją po folderach (assets/map + podfoldery)
                    $mapBaseDir = __DIR__ . '/assets/map';
                    $path = isset($_GET['path']) ? trim($_GET['path']) : '';
                    $path = str_replace('\\', '/', $path);
                    $path = trim($path, '/');
                    if (strpos($path, '..') !== false) {
                        echo json_encode(['success' => false, 'error' => 'Invalid path']);
                        break;
                    }
                    $fullPath = $path === '' ? $mapBaseDir : $mapBaseDir . '/' . $path;
                    if (!is_dir($fullPath)) {
                        echo json_encode(['success' => true, 'currentPath' => $path, 'folders' => [], 'files' => []]);
                        break;
                    }
                    $folders = [];
                    $files = [];
                    $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                    foreach (scandir($fullPath) as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $entryPath = $fullPath . '/' . $entry;
                        $relativePath = $path === '' ? $entry : $path . '/' . $entry;
                        if (is_dir($entryPath)) {
                            $folders[] = ['name' => $entry, 'path' => $relativePath];
                        } else {
                            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                            if (in_array($ext, $extensions)) {
                                $name = pathinfo($entry, PATHINFO_FILENAME);
                                $src = 'backend/assets/map/' . $relativePath;
                                $files[] = [
                                    'id' => $relativePath,
                                    'name' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                                    'src' => $src
                                ];
                            }
                        }
                    }
                    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    echo json_encode([
                        'success' => true,
                        'currentPath' => $path,
                        'folders' => $folders,
                        'files' => $files
                    ]);
                    break;

                case 'list-tokens':
                    // Lista tokenów z nawigacją po folderach (assets/tokens + podfoldery)
                    $tokenBaseDir = __DIR__ . '/assets/tokens';
                    $path = isset($_GET['path']) ? trim($_GET['path']) : '';
                    $path = str_replace('\\', '/', $path);
                    $path = trim($path, '/');
                    if (strpos($path, '..') !== false) {
                        echo json_encode(['success' => false, 'error' => 'Invalid path']);
                        break;
                    }
                    $fullPath = $path === '' ? $tokenBaseDir : $tokenBaseDir . '/' . $path;
                    if (!is_dir($fullPath)) {
                        echo json_encode(['success' => true, 'currentPath' => $path, 'folders' => [], 'files' => []]);
                        break;
                    }
                    $folders = [];
                    $files = [];
                    $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
                    foreach (scandir($fullPath) as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $entryPath = $fullPath . '/' . $entry;
                        $relativePath = $path === '' ? $entry : $path . '/' . $entry;
                        if (is_dir($entryPath)) {
                            $folders[] = ['name' => $entry, 'path' => $relativePath];
                        } else {
                            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                            if (in_array($ext, $extensions)) {
                                $name = pathinfo($entry, PATHINFO_FILENAME);
                                $src = 'backend/assets/tokens/' . $relativePath;
                                $files[] = [
                                    'id' => $relativePath,
                                    'name' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                                    'src' => $src
                                ];
                            }
                        }
                    }
                    usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    echo json_encode([
                        'success' => true,
                        'currentPath' => $path,
                        'folders' => $folders,
                        'files' => $files
                    ]);
                    break;

                case 'assets':
                    $backgroundAssets = [];
                    $bgDir = __DIR__ . '/assets/backgrounds';
                    // Elementy mapy i tokeny są ładowane przez list-map / list-tokens z nawigacją po folderach
                    if (is_dir($bgDir)) {
                        foreach (glob($bgDir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) as $file) {
                            $filename = basename($file);
                            $name = pathinfo($filename, PATHINFO_FILENAME);
                            $imageInfo = getimagesize($file);
                            $width = $imageInfo[0] ?? 0;
                            $height = $imageInfo[1] ?? 0;
                            
                            $backgroundAssets[] = [
                                'id' => $name,
                                'filename' => $filename,
                                'name' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                                'src' => 'backend/assets/backgrounds/' . $filename,
                                'width' => $width,
                                'height' => $height,
                                'gridWidth' => floor($width / 64),
                                'gridHeight' => floor($height / 64)
                            ];
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'backgroundAssets' => $backgroundAssets
                    ]);
                    break;

                case 'list-papers':
                    $papers = [];
                    $papersDir = __DIR__ . '/assets/papers';
                    if (is_dir($papersDir)) {
                        foreach (glob($papersDir . '/*.pdf') as $file) {
                            $filename = basename($file);
                            $name = pathinfo($filename, PATHINFO_FILENAME);
                            $papers[] = [
                                'id' => $filename,
                                'name' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                                'src' => 'backend/assets/papers/' . $filename
                            ];
                        }
                    }
                    usort($papers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    echo json_encode([
                        'success' => true,
                        'papers' => $papers
                    ]);
                    break;

                case 'get-paper':
                    $id = basename($_GET['id'] ?? '');
                    $filePath = __DIR__ . '/assets/papers/' . $id;
                    if ($id && preg_match('/\.pdf$/i', $id) && is_file($filePath)) {
                        header('Content-Type: application/pdf');
                        header('Content-Length: ' . filesize($filePath));
                        readfile($filePath);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Paper not found']);
                    }
                    break;

                case 'list-templates':
                    $templates = [];
                    $templatesDir = __DIR__ . '/assets/templates';
                    if (is_dir($templatesDir)) {
                        foreach (glob($templatesDir . '/*.html') as $file) {
                            $filename = basename($file);
                            $name = pathinfo($filename, PATHINFO_FILENAME);
                            $templates[] = [
                                'id' => $filename,
                                'name' => ucfirst(str_replace(['_', '-'], ' ', $name))
                            ];
                        }
                    }
                    usort($templates, fn($a, $b) => strcasecmp($a['name'], $b['name']));
                    echo json_encode(['success' => true, 'templates' => $templates]);
                    break;

                case 'get-template':
                    $id = basename($_GET['id'] ?? '');
                    $filePath = __DIR__ . '/assets/templates/' . $id;
                    if ($id && preg_match('/\.html?$/i', $id) && is_file($filePath)) {
                        header('Content-Type: text/html; charset=utf-8');
                        readfile($filePath);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Template not found']);
                    }
                    break;

                case 'rolls':
                    $rollsFile = __DIR__ . '/data/rolls.json';
                    $rolls = annotateRollsForSnackbar(loadRolls($rollsFile));
                    echo json_encode([
                        'success' => true,
                        'rolls' => $rolls,
                        'serverNow' => time()
                    ]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            break;

        case 'POST':
            // Dla większości akcji dane przychodzą jako JSON.
            // Wyjątkiem będzie upload plików (multipart/form-data), który ignoruje $input.
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                // ============================================
                // SCENE MANAGEMENT
                // ============================================
                
                case 'create-scene':
                    $state = getState();
                    $name = htmlspecialchars(mb_substr($input['name'] ?? 'New Scene', 0, 50));
                    $newScene = createEmptyScene($name);
                    $state['scenes'][] = $newScene;
                    $state = saveState($state);
                    echo json_encode([
                        'success' => true,
                        'scene' => ['id' => $newScene['id'], 'name' => $newScene['name']],
                        'version' => $state['version']
                    ]);
                    break;

                case 'delete-scene':
                    $state = getState();
                    $sceneId = $input['id'] ?? '';
                    
                    // Nie można usunąć jedynej sceny
                    if (count($state['scenes']) <= 1) {
                        echo json_encode(['success' => false, 'error' => 'Cannot delete last scene']);
                        break;
                    }
                    
                    $state['scenes'] = array_values(array_filter(
                        $state['scenes'],
                        fn($s) => $s['id'] !== $sceneId
                    ));
                    
                    // Jeśli usunięto aktywną scenę, przełącz na pierwszą
                    if ($state['activeSceneId'] === $sceneId) {
                        $state['activeSceneId'] = $state['scenes'][0]['id'];
                    }
                    
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'rename-scene':
                    $state = getState();
                    $sceneId = $input['id'] ?? '';
                    $name = htmlspecialchars(mb_substr($input['name'] ?? 'Scene', 0, 50));
                    
                    foreach ($state['scenes'] as &$scene) {
                        if ($scene['id'] === $sceneId) {
                            $scene['name'] = $name;
                            break;
                        }
                    }
                    
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'switch-scene':
                    $state = getState();
                    $sceneId = $input['id'] ?? '';
                    
                    // Sprawdź czy scena istnieje
                    $found = false;
                    foreach ($state['scenes'] as $scene) {
                        if ($scene['id'] === $sceneId) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        echo json_encode(['success' => false, 'error' => 'Scene not found']);
                        break;
                    }
                    
                    $state['activeSceneId'] = $sceneId;
                    $state = saveState($state);
                    
                    $activeScene = getActiveScene($state);
                    echo json_encode([
                        'success' => true,
                        'scene' => $activeScene,
                        'version' => $state['version']
                    ]);
                    break;

                case 'duplicate-scene':
                    $state = getState();
                    $sceneId = $input['id'] ?? '';
                    
                    $sourceScene = getSceneById($state, $sceneId);
                    if (!$sourceScene) {
                        echo json_encode(['success' => false, 'error' => 'Scene not found']);
                        break;
                    }
                    
                    $newScene = $sourceScene;
                    $newScene['id'] = 'scene_' . generateId();
                    $newScene['name'] = $sourceScene['name'] . ' (copy)';
                    
                    $state['scenes'][] = $newScene;
                    $state = saveState($state);
                    
                    echo json_encode([
                        'success' => true,
                        'scene' => ['id' => $newScene['id'], 'name' => $newScene['name']],
                        'version' => $state['version']
                    ]);
                    break;

                // ============================================
                // SCENE CONTENT (operate on active scene)
                // ============================================

                case 'set-background':
                    $state = getState();
                    $activeScene = getActiveScene($state);

                    // Podstawowe dane tła
                    $background = [
                        'src' => $input['src'],
                        'name' => $input['name'] ?? '',
                        'width' => intval($input['width'] ?? 0),
                        'height' => intval($input['height'] ?? 0),
                    ];

                    // Opcjonalne dopasowanie tła do siatki (przesunięcie i skala)
                    // W razie braku danych używamy wartości domyślnych: brak przesunięcia i skala 1.0
                    if (isset($input['offsetX'])) {
                        $background['offsetX'] = intval($input['offsetX']);
                    } else {
                        $background['offsetX'] = $activeScene['background']['offsetX'] ?? 0;
                    }

                    if (isset($input['offsetY'])) {
                        $background['offsetY'] = intval($input['offsetY']);
                    } else {
                        $background['offsetY'] = $activeScene['background']['offsetY'] ?? 0;
                    }

                    if (isset($input['scale'])) {
                        $background['scale'] = floatval($input['scale']);
                    } else {
                        $background['scale'] = isset($activeScene['background']['scale'])
                            ? floatval($activeScene['background']['scale'])
                            : 1.0;
                    }

                    if (isset($input['gridHidden'])) {
                        $background['gridHidden'] = (bool)$input['gridHidden'];
                    } else {
                        $background['gridHidden'] = $activeScene['background']['gridHidden'] ?? false;
                    }

                    $activeScene['background'] = $background;
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode([
                        'success' => true,
                        'background' => $activeScene['background'],
                        'version' => $state['version']
                    ]);
                    break;

                case 'remove-background':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $activeScene['background'] = null;
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'set-fog':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $activeScene['fogOfWar'] = [
                        'enabled' => (bool)($input['enabled'] ?? false),
                        'data' => $input['data'] ?? null
                    ];
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'update-fog':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    if (!isset($activeScene['fogOfWar'])) {
                        $activeScene['fogOfWar'] = ['enabled' => true, 'data' => null];
                    }
                    $activeScene['fogOfWar']['data'] = $input['data'] ?? null;
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'toggle-fog':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    if (!isset($activeScene['fogOfWar'])) {
                        $activeScene['fogOfWar'] = ['enabled' => false, 'data' => null];
                    }
                    $activeScene['fogOfWar']['enabled'] = (bool)($input['enabled'] ?? !$activeScene['fogOfWar']['enabled']);
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'enabled' => $activeScene['fogOfWar']['enabled'], 'version' => $state['version']]);
                    break;

                case 'add-map-element':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    
                    $x = intval($input['x']);
                    $y = intval($input['y']);
                    foreach ($activeScene['mapElements'] as $el) {
                        if ($el['x'] === $x && $el['y'] === $y) {
                            echo json_encode(['success' => false, 'error' => 'Position occupied']);
                            exit;
                        }
                    }
                    
                    $element = [
                        'id' => generateId(),
                        'assetId' => $input['assetId'],
                        'src' => $input['src'],
                        'x' => $x,
                        'y' => $y
                    ];
                    $activeScene['mapElements'][] = $element;
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'element' => $element, 'version' => $state['version']]);
                    break;

                case 'add-token':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    
                    $x = intval($input['x']);
                    $y = intval($input['y']);
                    foreach ($activeScene['tokens'] as $t) {
                        if ($t['x'] === $x && $t['y'] === $y) {
                            echo json_encode(['success' => false, 'error' => 'Position occupied by token']);
                            exit;
                        }
                    }
                    
                    $token = [
                        'id' => generateId(),
                        'assetId' => $input['assetId'],
                        'src' => $input['src'],
                        'x' => $x,
                        'y' => $y
                    ];
                    $activeScene['tokens'][] = $token;
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'token' => $token, 'version' => $state['version']]);
                    break;

                case 'move-token':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $tokenId = $input['id'];
                    $newX = intval($input['x']);
                    $newY = intval($input['y']);
                    
                    foreach ($activeScene['tokens'] as $t) {
                        if ($t['id'] !== $tokenId && $t['x'] === $newX && $t['y'] === $newY) {
                            echo json_encode(['success' => false, 'error' => 'Position occupied']);
                            exit;
                        }
                    }
                    
                    foreach ($activeScene['tokens'] as &$token) {
                        if ($token['id'] === $tokenId) {
                            $token['x'] = $newX;
                            $token['y'] = $newY;
                            break;
                        }
                    }
                    
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'update-token':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $tokenId = $input['id'];
                    
                    $found = false;
                    foreach ($activeScene['tokens'] as &$token) {
                        if ($token['id'] === $tokenId) {
                            // Aktualizuj tylko przekazane pola
                            if (isset($input['size'])) {
                                $token['size'] = floatval($input['size']);
                            }
                            if (isset($input['upperLabel'])) {
                                $token['upperLabel'] = $input['upperLabel'] !== null ? htmlspecialchars($input['upperLabel']) : null;
                            }
                            if (isset($input['lowerLabel'])) {
                                $token['lowerLabel'] = $input['lowerLabel'] !== null ? htmlspecialchars($input['lowerLabel']) : null;
                            }
                            $found = true;
                            break;
                        }
                    }
                    
                    if (!$found) {
                        echo json_encode(['success' => false, 'error' => 'Token not found']);
                        exit;
                    }
                    
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'remove-map-element':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $elementId = $input['id'];
                    $activeScene['mapElements'] = array_values(array_filter(
                        $activeScene['mapElements'],
                        fn($el) => $el['id'] !== $elementId
                    ));
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'remove-token':
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $tokenId = $input['id'];
                    $activeScene['tokens'] = array_values(array_filter(
                        $activeScene['tokens'],
                        fn($t) => $t['id'] !== $tokenId
                    ));
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'clear':
                    // Czyści tylko aktywną scenę
                    $state = getState();
                    $activeScene = getActiveScene($state);
                    $activeScene['background'] = null;
                    $activeScene['fogOfWar'] = ['enabled' => false, 'data' => null];
                    $activeScene['mapElements'] = [];
                    $activeScene['tokens'] = [];
                    updateActiveScene($state, $activeScene);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'roll':
                    $rollsFile = __DIR__ . '/data/rolls.json';
                    $rolls = loadRolls($rollsFile);
                    // Sprawdź typ rzutu
                    $rollType = $input['type'] ?? 'standard';
                    
                    if ($rollType === 'l5r') {
                        // Rzut L5R — faceId per die enables dice-face history on the frontend;
                        // totals are kept for legacy rolls and snackbar fallback.
                        $newRoll = [
                            'id' => generateId(),
                            'player' => htmlspecialchars(mb_substr($input['player'] ?? 'Anonymous', 0, 100)),
                            'label' => htmlspecialchars(mb_substr($input['label'] ?? '', 0, 200)),
                            'type' => 'l5r',
                            'dice' => array_map(function($die) {
                                $dieType = (($die['type'] ?? 'ring') === 'skill') ? 'skill' : 'ring';
                                $faceId = intval($die['faceId'] ?? 0);
                                $maxFace = $dieType === 'skill' ? 12 : 6;
                                if ($faceId < 1 || $faceId > $maxFace) {
                                    $faceId = 0;
                                }
                                return [
                                    'type' => $dieType,
                                    'faceId' => $faceId,
                                    'success' => intval($die['success'] ?? 0),
                                    'opportunity' => intval($die['opportunity'] ?? 0),
                                    'strife' => (bool)($die['strife'] ?? false),
                                    'exploded' => (bool)($die['exploded'] ?? false)
                                ];
                            }, $input['dice'] ?? []),
                            'totals' => [
                                'success' => intval($input['totals']['success'] ?? 0),
                                'opportunity' => intval($input['totals']['opportunity'] ?? 0),
                                'strife' => intval($input['totals']['strife'] ?? 0)
                            ],
                            'timestamp' => $input['timestamp'] ?? (time() * 1000)
                        ];
                    } else {
                        // Rzut standardowy
                        $newRoll = [
                            'id' => generateId(),
                            'player' => htmlspecialchars(mb_substr($input['player'] ?? 'Anonymous', 0, 100)),
                            'type' => 'standard',
                            'dice' => array_map(function($die) {
                                return [
                                    'type' => $die['type'] ?? 'd6',
                                    'sides' => intval($die['sides'] ?? 6),
                                    'result' => intval($die['result'] ?? 1)
                                ];
                            }, $input['dice'] ?? []),
                            'modifier' => intval($input['modifier'] ?? 0),
                            'total' => intval($input['total'] ?? 0),
                            'timestamp' => $input['timestamp'] ?? (time() * 1000)
                        ];
                    }
                    
                    array_unshift($rolls, $newRoll);
                    saveRolls($rollsFile, $rolls);
                    
                    // Zwiększ wersję stanu
                    $state = getState();
                    $state = saveState($state);
                    
                    echo json_encode(['success' => true, 'roll' => $newRoll, 'version' => $state['version']]);
                    break;

                case 'clear-rolls':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $rollsFile = __DIR__ . '/data/rolls.json';
                    saveRolls($rollsFile, []);
                    echo json_encode(['success' => true]);
                    break;

                case 'send-ping':
                    $state = getState();
                    $state['ping'] = [
                        'x' => intval($input['x']),
                        'y' => intval($input['y']),
                        'timestamp' => time() * 1000 + intval(microtime(true) * 1000) % 1000
                    ];
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'ping' => $state['ping'], 'version' => $state['version']]);
                    break;

                case 'clear-ping':
                    $state = getState();
                    $state['ping'] = null;
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'counter-add':
                    $input = is_array($input) ? $input : [];
                    $state = getState();
                    $clientId = sanitizeClientId($input['clientId'] ?? '');
                    if ($clientId === '') {
                        echo json_encode(['success' => false, 'error' => 'clientId required']);
                        break;
                    }
                    $type = $input['type'] ?? '';
                    if ($type !== 'manual' && $type !== 'timer') {
                        echo json_encode(['success' => false, 'error' => 'Invalid counter type']);
                        break;
                    }
                    $rawId = $input['id'] ?? '';
                    $id = sanitizeCounterId(is_string($rawId) ? $rawId : '');
                    if ($id === '') {
                        $id = generateId();
                    } else {
                        foreach ($state['counters'] as $ex) {
                            if (($ex['id'] ?? '') === $id) {
                                echo json_encode(['success' => false, 'error' => 'Counter id already exists']);
                                break 2;
                            }
                        }
                    }
                    $title = htmlspecialchars(mb_substr(is_string($input['title'] ?? null) ? $input['title'] : '', 0, COUNTER_TITLE_MAX));
                    $notesRaw = $input['notes'] ?? '';
                    $notes = htmlspecialchars(mb_substr(is_string($notesRaw) ? $notesRaw : '', 0, COUNTER_NOTES_MAX));
                    $now = time();
                    if ($type === 'manual') {
                        $counter = [
                            'id' => $id,
                            'type' => 'manual',
                            'ownerId' => $clientId,
                            'title' => $title,
                            'notes' => $notes,
                            'value' => intval($input['value'] ?? 0),
                        ];
                    } else {
                        $direction = (($input['direction'] ?? 'down') === 'up') ? 'up' : 'down';
                        $durationSec = intval($input['durationSec'] ?? 60);
                        $durationSec = min(COUNTER_DURATION_MAX_SEC, max(1, $durationSec));
                        if ($direction === 'down') {
                            $initSec = intval($input['initialDurationSec'] ?? $durationSec);
                            $initSec = min(COUNTER_DURATION_MAX_SEC, max(1, $initSec));
                            $counter = [
                                'id' => $id,
                                'type' => 'timer',
                                'ownerId' => $clientId,
                                'title' => $title,
                                'notes' => $notes,
                                'direction' => 'down',
                                'endsAt' => $now + $durationSec,
                                'initialDurationSec' => $initSec,
                            ];
                        } else {
                            $counter = [
                                'id' => $id,
                                'type' => 'timer',
                                'ownerId' => $clientId,
                                'title' => $title,
                                'notes' => $notes,
                                'direction' => 'up',
                                'startedAt' => $now,
                                'durationSec' => $durationSec,
                            ];
                        }
                    }
                    $state['counters'][] = $counter;
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'counter' => $counter, 'version' => $state['version']]);
                    break;

                case 'counter-update':
                    $input = is_array($input) ? $input : [];
                    $state = getState();
                    $clientId = sanitizeClientId($input['clientId'] ?? '');
                    $cid = $input['id'] ?? '';
                    if (!is_string($cid) || $cid === '') {
                        echo json_encode(['success' => false, 'error' => 'id required']);
                        break;
                    }
                    $idx = findCounterIndex($state, $cid);
                    if ($idx === null) {
                        echo json_encode(['success' => false, 'error' => 'Not found']);
                        break;
                    }
                    $counter = &$state['counters'][$idx];
                    if (!canEditCounter($counter, $clientId)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    if (array_key_exists('title', $input)) {
                        $counter['title'] = htmlspecialchars(mb_substr(is_string($input['title']) ? $input['title'] : '', 0, COUNTER_TITLE_MAX));
                    }
                    if (array_key_exists('notes', $input)) {
                        $counter['notes'] = htmlspecialchars(mb_substr(is_string($input['notes']) ? $input['notes'] : '', 0, COUNTER_NOTES_MAX));
                    }
                    if (($counter['type'] ?? '') === 'manual' && array_key_exists('value', $input)) {
                        $counter['value'] = intval($input['value']);
                    }
                    if (($counter['type'] ?? '') === 'timer') {
                        $now = time();
                        if (array_key_exists('direction', $input)) {
                            $counter['direction'] = ($input['direction'] === 'up') ? 'up' : 'down';
                        }
                        if (array_key_exists('endsAt', $input)) {
                            $counter['endsAt'] = intval($input['endsAt']);
                        }
                        if (array_key_exists('startedAt', $input)) {
                            $counter['startedAt'] = intval($input['startedAt']);
                        }
                        if (array_key_exists('durationSec', $input)) {
                            $counter['durationSec'] = min(COUNTER_DURATION_MAX_SEC, max(1, intval($input['durationSec'])));
                        }
                        if (array_key_exists('initialDurationSec', $input)) {
                            $counter['initialDurationSec'] = min(COUNTER_DURATION_MAX_SEC, max(1, intval($input['initialDurationSec'])));
                        }
                        if (($counter['direction'] ?? 'down') === 'down') {
                            unset($counter['startedAt'], $counter['durationSec']);
                            if (!isset($counter['endsAt'])) {
                                $counter['endsAt'] = $now + 60;
                            }
                            $counter['endsAt'] = min($now + COUNTER_DURATION_MAX_SEC, max($now, intval($counter['endsAt'])));
                        } else {
                            unset($counter['endsAt'], $counter['initialDurationSec']);
                            if (!isset($counter['startedAt'])) {
                                $counter['startedAt'] = $now;
                            }
                            if (!isset($counter['durationSec'])) {
                                $counter['durationSec'] = 60;
                            }
                            $counter['durationSec'] = min(COUNTER_DURATION_MAX_SEC, max(1, intval($counter['durationSec'])));
                        }
                    }
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'counter' => $state['counters'][$idx], 'version' => $state['version']]);
                    unset($counter);
                    break;

                case 'counter-delete':
                    $input = is_array($input) ? $input : [];
                    $state = getState();
                    $clientId = sanitizeClientId($input['clientId'] ?? '');
                    $cid = $input['id'] ?? '';
                    if (!is_string($cid) || $cid === '') {
                        echo json_encode(['success' => false, 'error' => 'id required']);
                        break;
                    }
                    $idx = findCounterIndex($state, $cid);
                    if ($idx === null) {
                        echo json_encode(['success' => false, 'error' => 'Not found']);
                        break;
                    }
                    $delCounter = $state['counters'][$idx];
                    if (!canEditCounter($delCounter, $clientId)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    array_splice($state['counters'], $idx, 1);
                    $state = saveState($state);
                    echo json_encode(['success' => true, 'version' => $state['version']]);
                    break;

                case 'delete-template':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $id = $input['id'] ?? '';
                    $id = is_string($id) ? basename(trim($id)) : '';
                    if ($id === '' || !preg_match('/\.html?$/i', $id)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid template id']);
                        break;
                    }
                    $filePath = resolveAssetFilePath('template', $id);
                    if ($filePath === null) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Template not found']);
                        break;
                    }
                    if (!unlink($filePath)) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Failed to delete template']);
                        break;
                    }
                    echo json_encode(['success' => true]);
                    break;

                case 'get-asset-usage':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $state = getState();
                    echo json_encode([
                        'success' => true,
                        'usage' => collectAssetUsageFromState($state),
                    ]);
                    break;

                case 'delete-assets':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }

                    $items = $input['items'] ?? null;
                    if (!is_array($items) || count($items) === 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'No items to delete']);
                        break;
                    }

                    $allowedTypes = ['token', 'map', 'background', 'template', 'paper'];
                    $state = getState();
                    $usage = collectAssetUsageFromState($state);
                    $deleted = [];
                    $blocked = [];
                    $errors = [];

                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            $errors[] = ['message' => 'Invalid item'];
                            continue;
                        }

                        $type = $item['type'] ?? '';
                        $type = is_string($type) ? trim($type) : '';
                        $id = $item['id'] ?? '';
                        $id = is_string($id) ? $id : '';

                        if (!in_array($type, $allowedTypes, true)) {
                            $errors[] = [
                                'type' => $type,
                                'id' => $id,
                                'message' => 'Invalid type',
                            ];
                            continue;
                        }

                        $normalizedId = normalizeDeleteAssetId($type, $id);
                        if ($normalizedId === '') {
                            $errors[] = [
                                'type' => $type,
                                'id' => $id,
                                'message' => 'Invalid asset id',
                            ];
                            continue;
                        }

                        if (isAssetInUse($type, $normalizedId, $usage)) {
                            $blocked[] = [
                                'type' => $type,
                                'id' => $normalizedId,
                                'scenes' => getAssetInUseScenes($type, $normalizedId, $usage),
                                'message' => 'Asset is in use',
                            ];
                            continue;
                        }

                        $filePath = resolveAssetFilePath($type, $normalizedId);
                        if ($filePath === null) {
                            $errors[] = [
                                'type' => $type,
                                'id' => $normalizedId,
                                'message' => 'File not found',
                            ];
                            continue;
                        }

                        if (!unlink($filePath)) {
                            $errors[] = [
                                'type' => $type,
                                'id' => $normalizedId,
                                'message' => 'Failed to delete file',
                            ];
                            continue;
                        }

                        $deleted[] = [
                            'type' => $type,
                            'id' => $normalizedId,
                        ];
                    }

                    echo json_encode([
                        'success' => count($deleted) > 0,
                        'deleted' => $deleted,
                        'blocked' => $blocked,
                        'errors' => $errors,
                    ]);
                    break;

                case 'save-template':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $name = $input['name'] ?? '';
                    $name = is_string($name) ? trim($name) : '';
                    $html = $input['html'] ?? '';
                    $html = is_string($html) ? $html : '';
                    $templateId = isset($input['id']) ? basename(trim($input['id'])) : '';
                    if (preg_match('/[^A-Za-z0-9_.-]/', $templateId) || !preg_match('/\.html?$/i', $templateId)) {
                        $templateId = '';
                    }
                    if ($name === '') {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Template name is required']);
                        break;
                    }
                    if (preg_match('/<\\s*script\\b/i', $html)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Template rejected – contains <script> tag']);
                        break;
                    }
                    $html = injectTemplateMeta($html, null);
                    $templatesDir = __DIR__ . '/assets/templates';
                    if (!is_dir($templatesDir)) {
                        mkdir($templatesDir, 0755, true);
                    }
                    if ($templateId !== '' && is_file($templatesDir . '/' . $templateId)) {
                        $filePath = $templatesDir . '/' . $templateId;
                        $filename = $templateId;
                    } else {
                        $slug = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
                        if ($slug === '') {
                            $slug = 'template';
                        }
                        $filename = $slug . '.html';
                        $filePath = $templatesDir . '/' . $filename;
                        $i = 1;
                        while (file_exists($filePath)) {
                            $filename = $slug . '-' . $i . '.html';
                            $filePath = $templatesDir . '/' . $filename;
                            $i++;
                        }
                    }
                    if (file_put_contents($filePath, $html) === false) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Failed to save template']);
                        break;
                    }
                    echo json_encode(['success' => true, 'id' => $filename]);
                    break;

                case 'clone-template':
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $cloneSourceId = isset($input['id']) ? basename(trim($input['id'])) : '';
                    if ($cloneSourceId === '' || !preg_match('/\.html?$/i', $cloneSourceId)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid template id']);
                        break;
                    }
                    $templatesDirClone = __DIR__ . '/assets/templates';
                    $srcPath = $templatesDirClone . '/' . $cloneSourceId;
                    if (!is_file($srcPath)) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Template not found']);
                        break;
                    }
                    $cloneName = isset($input['name']) ? trim($input['name']) : '';
                    $baseName = pathinfo($cloneSourceId, PATHINFO_FILENAME);
                    $baseSlug = $cloneName !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '_', $cloneName) : $baseName . '_clone';
                    if ($baseSlug === '') {
                        $baseSlug = 'template_clone';
                    }
                    $newFilename = $baseSlug . '.html';
                    $newPath = $templatesDirClone . '/' . $newFilename;
                    $idx = 1;
                    while (file_exists($newPath)) {
                        $newFilename = $baseSlug . '-' . $idx . '.html';
                        $newPath = $templatesDirClone . '/' . $newFilename;
                        $idx++;
                    }
                    $cloneHtml = file_get_contents($srcPath);
                    $cloneHtml = injectTemplateMeta($cloneHtml, 'custom-clone');
                    if (file_put_contents($newPath, $cloneHtml) === false) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Failed to clone template']);
                        break;
                    }
                    $displayName = ucfirst(str_replace(['_', '-'], ' ', pathinfo($newFilename, PATHINFO_FILENAME)));
                    echo json_encode(['success' => true, 'template' => ['id' => $newFilename, 'name' => $displayName]]);
                    break;

                case 'upload-asset':
                    // Upload materiałów – tylko dla Mistrza Gry
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }

                    // Oczekujemy multipart/form-data, więc ignorujemy $input z JSON
                    $type = $_POST['type'] ?? '';
                    $type = is_string($type) ? trim($type) : '';

                    $baseDirMap = [
                        'token' => __DIR__ . '/assets/tokens',
                        'map' => __DIR__ . '/assets/map',
                        'background' => __DIR__ . '/assets/backgrounds',
                        'template' => __DIR__ . '/assets/templates',
                        'paper' => __DIR__ . '/assets/papers',
                    ];

                    if (!isset($baseDirMap[$type])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid type']);
                        break;
                    }

                    $baseDir = $baseDirMap[$type];
                    if (!is_dir($baseDir)) {
                        mkdir($baseDir, 0755, true);
                    }

                    // Pomocnicza funkcja do sanityzacji nazw plików
                    $sanitizeFilename = function ($name) {
                        $name = basename($name);
                        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
                        if ($name === '' || $name === '.' || $name === '..') {
                            $name = 'file';
                        }
                        return $name;
                    };

                    // Pomocnicza funkcja do generowania unikalnej ścieżki
                    $makeUniquePath = function ($dir, $filename) {
                        $path = $dir . '/' . $filename;
                        if (!file_exists($path)) {
                            return $path;
                        }
                        $info = pathinfo($filename);
                        $base = $info['filename'] ?? 'file';
                        $ext = isset($info['extension']) && $info['extension'] !== '' ? ('.' . $info['extension']) : '';
                        $i = 1;
                        do {
                            $candidate = $dir . '/' . $base . '-' . $i . $ext;
                            $i++;
                        } while (file_exists($candidate));
                        return $candidate;
                    };

                    $result = [
                        'success' => false,
                        'uploaded' => [],
                        'errors' => [],
                    ];

                    // Upload wielu obrazków (token/map/background)
                    if (in_array($type, ['token', 'map', 'background'], true)) {
                        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'No files uploaded']);
                            break;
                        }

                        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
                        $names = $_FILES['files']['name'];
                        $tmpNames = $_FILES['files']['tmp_name'];
                        $errors = $_FILES['files']['error'];

                        $count = count($names);
                        for ($i = 0; $i < $count; $i++) {
                            $origName = $names[$i];
                            $tmpName = $tmpNames[$i];
                            $err = $errors[$i];

                            if ($err !== UPLOAD_ERR_OK) {
                                $msg = 'Upload error';
                                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                                    $msg = 'File too large for server limits';
                                }
                                $result['errors'][] = [
                                    'name' => $origName,
                                    'message' => $msg,
                                ];
                                continue;
                            }

                            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                            if (!in_array($ext, $allowedExtensions, true)) {
                                $result['errors'][] = [
                                    'name' => $origName,
                                    'message' => 'Invalid file type',
                                ];
                                continue;
                            }

                            $safeName = $sanitizeFilename($origName);
                            $targetPath = $makeUniquePath($baseDir, $safeName);

                            if (!move_uploaded_file($tmpName, $targetPath)) {
                                $result['errors'][] = [
                                    'name' => $origName,
                                    'message' => 'Failed to save file',
                                ];
                                continue;
                            }

                            $result['uploaded'][] = [
                                'originalName' => $origName,
                                'storedName' => basename($targetPath),
                                'type' => $type,
                            ];
                        }

                        $result['success'] = count($result['uploaded']) > 0;
                        echo json_encode($result);
                        break;
                    }

                    // Upload szablonu HTML (pojedynczy plik)
                    if ($type === 'template') {
                        if (empty($_FILES['file'])) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                            break;
                        }

                        $file = $_FILES['file'];
                        $origName = $file['name'] ?? '';
                        $tmpName = $file['tmp_name'] ?? '';
                        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

                        if ($err !== UPLOAD_ERR_OK) {
                            $msg = 'Upload error';
                            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                                $msg = 'File too large for server limits';
                            }
                            echo json_encode(['success' => false, 'error' => $msg]);
                            break;
                        }

                        if (!preg_match('/\\.html?$/i', $origName)) {
                            echo json_encode(['success' => false, 'error' => 'Invalid template extension']);
                            break;
                        }

                        $safeName = $sanitizeFilename($origName);
                        $targetPath = $makeUniquePath($baseDir, $safeName);

                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            echo json_encode(['success' => false, 'error' => 'Failed to save template']);
                            break;
                        }

                        // Skan na obecność <script>
                        $content = @file_get_contents($targetPath);
                        if ($content === false) {
                            @unlink($targetPath);
                            echo json_encode(['success' => false, 'error' => 'Failed to read template']);
                            break;
                        }

                        if (preg_match('/<\\s*script\\b/i', $content)) {
                            @unlink($targetPath);
                            echo json_encode([
                                'success' => false,
                                'error' => 'Template rejected – contains <script> tag',
                            ]);
                            break;
                        }

                        echo json_encode([
                            'success' => true,
                            'uploaded' => [[
                                'originalName' => $origName,
                                'storedName' => basename($targetPath),
                                'type' => $type,
                            ]],
                        ]);
                        break;
                    }

                    // Upload PDF (pojedynczy plik)
                    if ($type === 'paper') {
                        // Obsługa przypadku zbyt dużego pliku – serwer może w ogóle nie utworzyć wpisu w $_FILES
                        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
                        if (empty($_FILES['file']) && $contentLength > 0) {
                            echo json_encode([
                                'success' => false,
                                'error' => 'File too large for server limits – upload manually via FTP',
                                'code' => 'file_too_large',
                            ]);
                            break;
                        }

                        if (empty($_FILES['file'])) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                            break;
                        }

                        $file = $_FILES['file'];
                        $origName = $file['name'] ?? '';
                        $tmpName = $file['tmp_name'] ?? '';
                        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

                        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                            echo json_encode([
                                'success' => false,
                                'error' => 'File too large for server limits – such big files must be uploaded manually.',
                                'code' => 'file_too_large',
                            ]);
                            break;
                        }

                        if ($err !== UPLOAD_ERR_OK) {
                            echo json_encode(['success' => false, 'error' => 'Upload error']);
                            break;
                        }

                        if (!preg_match('/\\.pdf$/i', $origName)) {
                            echo json_encode(['success' => false, 'error' => 'Invalid PDF extension']);
                            break;
                        }

                        $safeName = $sanitizeFilename($origName);
                        $targetPath = $makeUniquePath($baseDir, $safeName);

                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            echo json_encode(['success' => false, 'error' => 'Failed to save PDF']);
                            break;
                        }

                        echo json_encode([
                            'success' => true,
                            'uploaded' => [[
                                'originalName' => $origName,
                                'storedName' => basename($targetPath),
                                'type' => $type,
                            ]],
                        ]);
                        break;
                    }

                    // Fallback – nieobsługiwany typ (nie powinno się zdarzyć)
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid type']);
                    break;
                    
                case 'ttrpg-set-key':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $result = \FreeRoll\Ttrpg\Actions::setKey(is_array($input) ? $input : []);
                    if (!empty($result['success'])) {
                        $result['version'] = ttrpgBumpStateVersion();
                    }
                    echo json_encode($result);
                    break;

                case 'ttrpg-clear-key':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $result = \FreeRoll\Ttrpg\Actions::clearKey();
                    $result['version'] = ttrpgBumpStateVersion();
                    echo json_encode($result);
                    break;

                case 'ttrpg-select-campaign':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $result = \FreeRoll\Ttrpg\Actions::selectCampaign(is_array($input) ? $input : []);
                    if (!empty($result['success'])) {
                        $result['version'] = ttrpgBumpStateVersion();
                    }
                    echo json_encode($result);
                    break;

                case 'ttrpg-proxy':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    echo json_encode(\FreeRoll\Ttrpg\Actions::proxy(
                        is_array($input) ? $input : [],
                        isGameMaster()
                    ));
                    break;

                case 'ttrpg-upload-asset':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $campaignId = intval($_POST['campaignId'] ?? $_POST['campaign_id'] ?? 0);
                    $file = $_FILES['image'] ?? null;
                    if (!is_array($file)) {
                        echo json_encode(['success' => false, 'error' => 'image file required']);
                        break;
                    }
                    echo json_encode(\FreeRoll\Ttrpg\Actions::uploadAsset($campaignId, $file));
                    break;

                case 'ttrpg-upload-handbook':
                    if (!ttrpgRequireReady()) {
                        break;
                    }
                    if (!isGameMaster()) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'error' => 'Forbidden']);
                        break;
                    }
                    $file = $_FILES['pdf'] ?? null;
                    if (!is_array($file)) {
                        echo json_encode(['success' => false, 'error' => 'pdf file required']);
                        break;
                    }
                    echo json_encode(\FreeRoll\Ttrpg\Actions::uploadHandbook($file, [
                        'title' => $_POST['title'] ?? '',
                        'language' => $_POST['language'] ?? null,
                        'campaign_id' => $_POST['campaign_id'] ?? $_POST['campaignId'] ?? null,
                    ]));
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}