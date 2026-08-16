<?php

namespace App\Livewire\Admin;

use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;
use App\Services\Admin\TelemetryAggregator;
use Livewire\Component;

class FilesIndex extends Component
{
    public function deleteFile(int $tableId, string $relative, TableDiskReader $reader): void
    {
        $table = VttTable::query()->with('user')->find($tableId);
        if (! $table) {
            session()->flash('admin_error', 'Nie znaleziono stołu.');

            return;
        }
        if ($reader->deleteAsset($table, $relative)) {
            session()->flash('admin_status', 'Usunięto plik: '.$relative);
        } else {
            session()->flash('admin_error', 'Nie udało się usunąć pliku.');
        }
    }

    public function render(TelemetryAggregator $aggregator)
    {
        return view('livewire.admin.files-index', [
            'files' => $aggregator->allFiles(),
        ])->layout('layouts.admin', [
            'title' => 'Pliki',
            'heading' => 'Pliki graczy',
        ]);
    }
}
