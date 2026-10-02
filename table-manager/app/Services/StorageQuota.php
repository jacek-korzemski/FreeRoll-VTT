<?php

namespace App\Services;

use App\Models\User;
use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;

class StorageQuota
{
    public function __construct(private TableDiskReader $reader) {}

    public function tableLimitBytes(?VttTable $table = null): int
    {
        if ($table !== null) {
            return max(0, $table->uploadQuotaMb()) * 1048576;
        }

        return max(0, (int) config('vtt.max_table_upload_mb', 50)) * 1048576;
    }

    public function userLimitBytes(User $user): int
    {
        $explicit = config('vtt.max_user_upload_mb');
        if ($explicit !== null && $explicit !== '') {
            return max(0, (int) $explicit) * 1048576;
        }

        $tables = $user->relationLoaded('vttTables')
            ? $user->vttTables
            : $user->vttTables()->get();

        $granted = 0;
        foreach ($tables as $table) {
            $granted += $this->tableLimitBytes($table);
        }

        $remaining = max(0, $user->maxTables() - $tables->count());

        return $granted + ($remaining * $this->tableLimitBytes());
    }

    public function tableUsageBytes(VttTable $table): int
    {
        return $this->reader->assetUsageBytes($table);
    }

    public function userUsageBytes(User $user): int
    {
        $total = 0;
        foreach ($user->vttTables as $table) {
            $total += $this->tableUsageBytes($table);
        }

        return $total;
    }

    /**
     * @param  iterable<int, VttTable>  $tables
     * @return array{tables: array<int, int>, tableLimits: array<int, int>, userUsed: int, tableLimit: int, userLimit: int}
     */
    public function snapshotForTables(User $user, iterable $tables): array
    {
        $usages = [];
        $limits = [];
        $userUsed = 0;
        foreach ($tables as $table) {
            $used = $this->tableUsageBytes($table);
            $usages[$table->id] = $used;
            $limits[$table->id] = $this->tableLimitBytes($table);
            $userUsed += $used;
        }

        return [
            'tables' => $usages,
            'tableLimits' => $limits,
            'userUsed' => $userUsed,
            'tableLimit' => $this->tableLimitBytes(),
            'userLimit' => $this->userLimitBytes($user),
        ];
    }
}
