<?php
/**
 * Lightweight VTT telemetry: logins, presence windows, interactions.
 * Writes under backend/data/telemetry/. Failures are swallowed so the table still works.
 */

const VTT_TELEMETRY_IDLE_SEC = 45;
const VTT_TELEMETRY_PRESENCE_THROTTLE_SEC = 5;
const VTT_TELEMETRY_MAX_EVENTS = 8000;
const VTT_TELEMETRY_MAX_BYTES = 2097152;
const VTT_TELEMETRY_MAX_SESSIONS = 500;

function vttTelemetrySetDir(?string $dir): void
{
    $GLOBALS['VTT_TELEMETRY_DIR'] = $dir;
}

function vttTelemetryDir(): string
{
    if (!empty($GLOBALS['VTT_TELEMETRY_DIR']) && is_string($GLOBALS['VTT_TELEMETRY_DIR'])) {
        return rtrim($GLOBALS['VTT_TELEMETRY_DIR'], '/\\');
    }

    return dirname(__DIR__).DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'telemetry';
}

function vttTelemetryEnsureDir(): ?string
{
    $dir = vttTelemetryDir();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        @file_put_contents($dir.DIRECTORY_SEPARATOR.'.htaccess', "Order Allow,Deny\nDeny from all\n");
    }

    return $dir;
}

function vttTelemetrySanitizeId($id): string
{
    if (!is_string($id)) {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9-]{8,64}$/', $id)) {
        return '';
    }

    return $id;
}

function vttTelemetryClientId(): string
{
    $header = $_SERVER['HTTP_X_VTT_CLIENT_ID'] ?? '';
    $post = $_POST['client_id'] ?? '';

    $id = vttTelemetrySanitizeId($header);
    if ($id !== '') {
        return $id;
    }

    return vttTelemetrySanitizeId(is_string($post) ? $post : '');
}

function vttTelemetryRole(?bool $isGm = null): string
{
    if ($isGm === true) {
        return 'gm';
    }
    if ($isGm === false) {
        return 'player';
    }
    if (!empty($_SESSION['vtt_is_gm'])) {
        return 'gm';
    }

    return 'player';
}

function vttTelemetryIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($ip) ? substr($ip, 0, 45) : '';
}

function vttTelemetryPlayerName(): ?string
{
    $name = $_SERVER['HTTP_X_VTT_PLAYER_NAME'] ?? '';
    if (!is_string($name) || $name === '') {
        return null;
    }
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    if (str_contains($name, '%')) {
        $decoded = rawurldecode($name);
        $validUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($decoded, 'UTF-8')
            : (bool) preg_match('//u', $decoded);
        if ($validUtf8) {
            $name = $decoded;
        }
    }
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    return function_exists('mb_substr') ? mb_substr($name, 0, 80) : substr($name, 0, 80);
}

function vttTelemetryReadJson(string $path, $default)
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : $default;
}

function vttTelemetryWriteJson(string $path, array $data): void
{
    $tmp = $path.'.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return;
    }
    @rename($tmp, $path);
}

function vttTelemetryEmptyPresence(): array
{
    return [
        'updatedAt' => 0,
        'writtenAt' => 0,
        'openSession' => null,
        'clients' => [],
    ];
}

/**
 * @param  array<string, mixed>  $extra
 */
function vttTelemetryAppendEvent(string $type, array $extra = []): void
{
    $dir = vttTelemetryEnsureDir();
    if ($dir === null) {
        return;
    }
    $path = $dir.DIRECTORY_SEPARATOR.'events.jsonl';
    $event = array_merge([
        'type' => $type,
        'ts' => time(),
        'role' => $extra['role'] ?? vttTelemetryRole(),
        'clientId' => $extra['clientId'] ?? vttTelemetryClientId(),
        'ip' => vttTelemetryIp(),
    ], $extra);
    $line = json_encode($event, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    @file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
    vttTelemetryRotateEvents($path);
}

function vttTelemetryRotateEvents(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $size = @filesize($path);
    if ($size !== false && $size < VTT_TELEMETRY_MAX_BYTES) {
        $count = 0;
        $fh = @fopen($path, 'r');
        if ($fh) {
            while (!feof($fh)) {
                if (fgets($fh) !== false) {
                    $count++;
                }
                if ($count > VTT_TELEMETRY_MAX_EVENTS) {
                    break;
                }
            }
            fclose($fh);
        }
        if ($count <= VTT_TELEMETRY_MAX_EVENTS && ($size === false || $size < VTT_TELEMETRY_MAX_BYTES)) {
            return;
        }
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || $lines === []) {
        return;
    }
    $keep = (int) floor(count($lines) / 2);
    $slice = array_slice($lines, -$keep);
    @file_put_contents($path, implode("\n", $slice).(count($slice) ? "\n" : ''), LOCK_EX);
}

function vttTelemetryRecordLogin(bool $success, bool $isGm): void
{
    vttTelemetryAppendEvent($success ? 'login' : 'login_fail', [
        'role' => $isGm ? 'gm' : 'player',
        'clientId' => vttTelemetryClientId(),
    ]);
    if ($success) {
        vttTelemetryTouchPresence('login', $isGm);
    }
}

function vttTelemetryRecordLogout(): void
{
    vttTelemetryAppendEvent('logout');
}

function vttTelemetryRecordInteraction(string $action): void
{
    $action = preg_replace('/[^a-z0-9_-]/i', '', $action) ?? '';
    if ($action === '') {
        return;
    }
    vttTelemetryAppendEvent('interaction', ['action' => $action]);
}

function vttTelemetryTouchPresence(string $action = '', ?bool $isGm = null): void
{
    $dir = vttTelemetryEnsureDir();
    if ($dir === null) {
        return;
    }
    $path = $dir.DIRECTORY_SEPARATOR.'presence.json';
    $now = time();
    $presence = vttTelemetryReadJson($path, vttTelemetryEmptyPresence());
    if (!isset($presence['clients']) || !is_array($presence['clients'])) {
        $presence['clients'] = [];
    }

    $clientId = vttTelemetryClientId();
    $role = vttTelemetryRole($isGm);
    $playerName = vttTelemetryPlayerName();
    $isNewClient = $clientId !== '' && !isset($presence['clients'][$clientId]);

    $closed = vttTelemetryFinalizeStale($presence, $now);
    if ($closed !== null) {
        vttTelemetryPushSession($dir, $closed);
    }

    if ($clientId !== '') {
        $existing = $presence['clients'][$clientId] ?? [];
        $presence['clients'][$clientId] = [
            'clientId' => $clientId,
            'role' => $role,
            'firstSeen' => (int) ($existing['firstSeen'] ?? $now),
            'lastSeen' => $now,
            'playerName' => $playerName ?? ($existing['playerName'] ?? null),
        ];
    }

    $onlineIds = vttTelemetryOnlineIds($presence, $now);
    if ($onlineIds !== []) {
        if (!is_array($presence['openSession'] ?? null)) {
            $presence['openSession'] = [
                'startedAt' => $now,
                'peakClients' => count($onlineIds),
                'clientIds' => $onlineIds,
            ];
        } else {
            $presence['openSession']['peakClients'] = max(
                (int) ($presence['openSession']['peakClients'] ?? 1),
                count($onlineIds)
            );
            $merged = array_values(array_unique(array_merge(
                $presence['openSession']['clientIds'] ?? [],
                $onlineIds
            )));
            $presence['openSession']['clientIds'] = $merged;
        }
    }

    $presence['updatedAt'] = $now;
    $writtenAt = (int) ($presence['writtenAt'] ?? 0);
    $mustWrite = $closed !== null
        || $writtenAt === 0
        || ($now - $writtenAt) >= VTT_TELEMETRY_PRESENCE_THROTTLE_SEC
        || $isNewClient;

    if (!$mustWrite && is_file($path) && ($now - $writtenAt) < VTT_TELEMETRY_PRESENCE_THROTTLE_SEC) {
        return;
    }

    $presence['writtenAt'] = $now;
    vttTelemetryWriteJson($path, $presence);
}

/**
 * @param  array<string, mixed>  $presence
 * @return list<string>
 */
function vttTelemetryOnlineIds(array $presence, int $now): array
{
    $ids = [];
    foreach ($presence['clients'] ?? [] as $id => $client) {
        if (!is_array($client)) {
            continue;
        }
        $last = (int) ($client['lastSeen'] ?? 0);
        if ($last > 0 && ($now - $last) < VTT_TELEMETRY_IDLE_SEC) {
            $ids[] = (string) ($client['clientId'] ?? $id);
        }
    }

    return $ids;
}

/**
 * @param  array<string, mixed>  $presence
 * @return array<string, mixed>|null  Closed session payload
 */
function vttTelemetryFinalizeStale(array &$presence, int $now): ?array
{
    $open = $presence['openSession'] ?? null;
    if (!is_array($open)) {
        return null;
    }
    $online = vttTelemetryOnlineIds($presence, $now);
    if ($online !== []) {
        return null;
    }
    $started = (int) ($open['startedAt'] ?? $now);
    $ended = $now;
    foreach ($presence['clients'] ?? [] as $client) {
        if (!is_array($client)) {
            continue;
        }
        $ended = max($ended, (int) ($client['lastSeen'] ?? 0));
    }
    if ($ended < $started) {
        $ended = $started;
    }
    $session = [
        'startedAt' => $started,
        'endedAt' => $ended,
        'durationSec' => max(0, $ended - $started),
        'peakClients' => (int) ($open['peakClients'] ?? 1),
        'clientIds' => array_values($open['clientIds'] ?? []),
    ];
    $presence['openSession'] = null;

    return $session;
}

function vttTelemetryPushSession(string $dir, array $session): void
{
    $path = $dir.DIRECTORY_SEPARATOR.'sessions.json';
    $list = vttTelemetryReadJson($path, []);
    if (!is_array($list)) {
        $list = [];
    }
    $list[] = $session;
    if (count($list) > VTT_TELEMETRY_MAX_SESSIONS) {
        $list = array_slice($list, -VTT_TELEMETRY_MAX_SESSIONS);
    }
    vttTelemetryWriteJson($path, array_values($list));
}
