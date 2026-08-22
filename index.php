<?php
require_once __DIR__ . '/deploy-env.php';

$cfg = getDeployConfig(__DIR__);
startVttSession($cfg);
$login = $cfg['loginStrings'];
$basePath = $cfg['basePath'];

$vttTelemetryFile = __DIR__ . '/backend/include/telemetry.php';
if (is_file($vttTelemetryFile)) {
    require_once $vttTelemetryFile;
}

$error = '';

if (isset($_GET['logout'])) {
    if (function_exists('vttTelemetryRecordLogout')) {
        vttTelemetryRecordLogout();
    }
    unset($_SESSION['vtt_authenticated']);
    unset($_SESSION['vtt_is_gm']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $isGm = isset($_POST['is_gm']) && $_POST['is_gm'] === '1';
    $password = $_POST['password'];

    if ($isGm) {
        if ($password === $cfg['gmPassword']) {
            $_SESSION['vtt_authenticated'] = true;
            $_SESSION['vtt_is_gm'] = true;
            if (function_exists('vttTelemetryRecordLogin')) {
                vttTelemetryRecordLogin(true, true);
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        if (function_exists('vttTelemetryRecordLogin')) {
            vttTelemetryRecordLogin(false, true);
        }
        $error = $login['loginError'];
    } else {
        if ($password === $cfg['password']) {
            $_SESSION['vtt_authenticated'] = true;
            $_SESSION['vtt_is_gm'] = false;
            if (function_exists('vttTelemetryRecordLogin')) {
                vttTelemetryRecordLogin(true, false);
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        if (function_exists('vttTelemetryRecordLogin')) {
            vttTelemetryRecordLogin(false, false);
        }
        $error = $login['loginError'];
    }
}

if (!isset($_SESSION['vtt_authenticated']) || $_SESSION['vtt_authenticated'] !== true):
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($cfg['language']) ?>" <?= colorTemplateHtmlAttributes($cfg) ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($login['loginTitle']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--color-bg) 0%, var(--color-panel) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-strong);
        }
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem 3rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        h1 { margin-bottom: 0.5rem; color: var(--color-accent); font-size: 2rem; }
        .subtitle { color: var(--color-text-dim); margin-bottom: 2rem; font-size: 0.9rem; }
        .error {
            background: rgba(var(--color-accent-rgb), 0.2);
            border: 1px solid var(--color-accent);
            color: var(--color-accent-hover);
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        form { display: flex; flex-direction: column; gap: 1rem; }
        input[type="password"] {
            padding: 0.875rem 1rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-text-strong);
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input[type="password"]:focus { outline: none; border-color: var(--color-accent); }
        input[type="password"]::placeholder { color: var(--color-text-dim); }
        .gm-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            cursor: pointer;
            user-select: none;
        }
        .gm-checkbox input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            cursor: pointer;
            accent-color: var(--color-accent);
        }
        .gm-checkbox label {
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--color-text-muted);
        }
        button {
            padding: 0.875rem;
            background: var(--color-accent);
            color: var(--color-text-strong);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        button:hover { background: var(--color-accent-hover); }
        button:active { transform: scale(0.98); }
        .dice { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="dice">🎲</div>
        <h1><?= htmlspecialchars($login['loginTitle']) ?></h1>
        <p class="subtitle"><?= htmlspecialchars($login['loginSubtitle']) ?></p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="client_id" id="vtt_client_id" value="">
            <input type="password" name="password" placeholder="<?= htmlspecialchars($login['loginPlaceholder']) ?>" required autofocus>
            <div class="gm-checkbox">
                <input type="checkbox" id="is_gm" name="is_gm" value="1">
                <label for="is_gm"><?= htmlspecialchars($login['loginGmCheckbox']) ?></label>
            </div>
            <button type="submit"><?= htmlspecialchars($login['loginSubmit']) ?></button>
        </form>
    </div>
    <script>
    (function () {
        var key = 'vtt_client_id';
        var el = document.getElementById('vtt_client_id');
        if (!el) return;
        try {
            var id = localStorage.getItem(key);
            if (!id || !/^[a-zA-Z0-9-]{8,64}$/.test(id)) {
                id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('fb-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12));
                localStorage.setItem(key, id);
            }
            el.value = id;
        } catch (e) {}
    })();
    </script>
</body>
</html>
<?php
exit;
endif;

$vttConfig = [
    'basePath' => $cfg['basePath'],
    'language' => $cfg['language'],
    'enableL5r' => $cfg['enableL5r'],
    'colorTemplate' => $cfg['colorTemplate'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($cfg['language']) ?>" <?= colorTemplateHtmlAttributes($cfg) ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($login['appTitle']) ?></title>
    <script>window.__VTT_CONFIG__=<?= json_encode($vttConfig, JSON_UNESCAPED_SLASHES) ?>;</script>
    <script type="module" crossorigin src="<?= htmlspecialchars($basePath) ?>assets/index.js"></script>
    <link rel="stylesheet" crossorigin href="<?= htmlspecialchars($basePath) ?>assets/index.css">
    <style>
        .logout-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            padding: 0.5rem 1rem;
            background: rgba(var(--color-accent-rgb), 0.8);
            color: var(--color-text-strong);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        .logout-btn:hover { background: var(--color-accent); }
    </style>
</head>
<body>
    <a href="?logout=1" class="logout-btn">🚪 <?= htmlspecialchars($login['logout']) ?></a>
    <div id="root"></div>
</body>
</html>
