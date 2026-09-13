<?php

namespace App\Services;

use App\Models\User;
use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;

class StorageQuota
{
    public function __construct(private TableDiskReader $reader) {}

    public function tableLimitBytes(): int
    {
        return max(0, (int) config('vtt.max_table_upload_mb', 50)) * 1048576;
    }

    public function userLimitBytes(): int
    {
        $explicit = config('vtt.max_user_upload_mb');
        if ($explicit !== null && $explicit !== '') {
            return max(0, (int) $explicit) * 1048576;
        }

        return $this->tableLimitBytes() * max(1, (int) config('vtt.max_tables', 3));
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
     * @return array{tables: array<int, int>, userUsed: int, tableLimit: int, userLimit: int}
     */
    public function snapshotForTables(iterable $tables): array
    {
        $usages = [];
        $userUsed = 0;
        foreach ($tables as $table) {
            $used = $this->tableUsageBytes($table);
            $usages[$table->id] = $used;
            $userUsed += $used;
        }

        return [
            'tables' => $usages,
            'userUsed' => $userUsed,
            'tableLimit' => $this->tableLimitBytes(),
            'userLimit' => $this->userLimitBytes(),
        ];
    }
}
