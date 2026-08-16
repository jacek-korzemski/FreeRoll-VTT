<?php

namespace App\Services\Admin;

use App\Models\VttTable;

class TelemetryAggregator
{
    public function __construct(private TableDiskReader $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $tables = VttTable::query()->with('user')->latest()->get();
        $onlineTables = 0;
        $onlineClients = 0;
        $logins24h = 0;
        $uniqueClients = 0;
        $sessionSeconds = 0;
        $interactions24h = 0;

        foreach ($tables as $table) {
            $t = $this->reader->telemetry($table);
            if ($t['onlineCount'] > 0) {
                $onlineTables++;
            }
            $onlineClients += $t['onlineCount'];
            $logins24h += $t['logins24h'];
            $uniqueClients += $t['uniqueClients'];
            $sessionSeconds += $t['sessionSecondsTotal'];
            $interactions24h += $t['interactions24h'];
        }

        return [
            'tableCount' => $tables->count(),
            'onlineTables' => $onlineTables,
            'onlineClients' => $onlineClients,
            'logins24h' => $logins24h,
            'uniqueClients' => $uniqueClients,
            'sessionSecondsTotal' => $sessionSeconds,
            'interactions24h' => $interactions24h,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tableRows(): array
    {
        $rows = [];
        foreach (VttTable::query()->with('user')->latest()->get() as $table) {
            $t = $this->reader->telemetry($table);
            $rows[] = [
                'table' => $table,
                'onlineCount' => $t['onlineCount'],
                'lastSeen' => $t['lastSeen'],
                'uniqueClients' => $t['uniqueClients'],
                'sessionSecondsTotal' => $t['sessionSecondsTotal'],
                'logins' => $t['logins'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        $tables = VttTable::query()->with('user')->latest()->get();
        $allSessions = [];
        $logins = ['player' => 0, 'gm' => 0, 'fail' => 0];
        $actionCounts = [];
        $hourly = array_fill(0, 24, 0);
        $unique = 0;
        $sessionSeconds = 0;

        foreach ($tables as $table) {
            $t = $this->reader->telemetry($table);
            $unique += $t['uniqueClients'];
            $sessionSeconds += $t['sessionSecondsTotal'];
            foreach (['player', 'gm', 'fail'] as $k) {
                $logins[$k] += $t['logins'][$k] ?? 0;
            }
            foreach ($t['actionCounts'] as $action => $count) {
                $actionCounts[$action] = ($actionCounts[$action] ?? 0) + $count;
            }
            foreach ($t['hourly24'] as $hour => $count) {
                $hourly[$hour] += $count;
            }
            foreach ($t['sessions'] as $session) {
                if (! is_array($session)) {
                    continue;
                }
                $allSessions[] = $session + [
                    'tableId' => $table->id,
                    'tableName' => $table->name,
                    'owner' => $table->user->username ?? '',
                ];
            }
            if (is_array($t['openSession'])) {
                $allSessions[] = $t['openSession'] + [
                    'tableId' => $table->id,
                    'tableName' => $table->name,
                    'owner' => $table->user->username ?? '',
                    'open' => true,
                ];
            }
        }

        usort($allSessions, fn ($a, $b) => ((int) ($b['startedAt'] ?? 0)) <=> ((int) ($a['startedAt'] ?? 0)));
        arsort($actionCounts);
        $maxHour = max(1, ...array_values($hourly));

        return [
            'sessions' => $allSessions,
            'logins' => $logins,
            'uniqueClients' => $unique,
            'sessionSecondsTotal' => $sessionSeconds,
            'topActions' => array_slice($actionCounts, 0, 15, true),
            'hourly24' => $hourly,
            'hourlyMax' => $maxHour,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allFiles(): array
    {
        $files = [];
        foreach (VttTable::query()->with('user')->latest()->get() as $table) {
            foreach ($this->reader->listAssets($table) as $asset) {
                $files[] = $asset + [
                    'tableId' => $table->id,
                    'tableName' => $table->name,
                    'owner' => $table->user->username ?? '',
                ];
            }
        }
        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' s';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        if ($m < 60) {
            return $s > 0 ? $m.' min '.$s.' s' : $m.' min';
        }
        $h = intdiv($m, 60);
        $m = $m % 60;

        return $m > 0 ? $h.' h '.$m.' min' : $h.' h';
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }
}
