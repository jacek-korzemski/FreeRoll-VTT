<?php

namespace App\Livewire\Admin;

use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;
use Livewire\Component;

class TableShow extends Component
{
    public VttTable $table;

    public bool $confirmingDelete = false;

    public bool $confirmingReset = false;

    public function mount(VttTable $table): void
    {
        $this->table = $table->load('user');
    }

    public function resetState(TableDiskReader $reader): void
    {
        $reader->resetGameState($this->table);
        $this->confirmingReset = false;
        session()->flash('admin_status', 'Stan gry i rzuty kości zostały zresetowane.');
    }

    public function deleteFile(string $relative, TableDiskReader $reader): void
    {
        if ($reader->deleteAsset($this->table, $relative)) {
            session()->flash('admin_status', 'Usunięto plik: '.$relative);
        } else {
            session()->flash('admin_error', 'Nie udało się usunąć pliku.');
        }
    }

    public function deleteTable(TableDiskReader $reader): mixed
    {
        $this->table->loadMissing('user');
        $reader->destroyTable($this->table);

        return redirect()->route('admin.tables')->with('admin_status', 'Stół został usunięty.');
    }

    public function render(TableDiskReader $reader)
    {
        $telemetry = $reader->telemetry($this->table);
        $recentEvents = array_slice(array_reverse($telemetry['events']), 0, 40);

        return view('livewire.admin.table-show', [
            'telemetry' => $telemetry,
            'assets' => $reader->listAssets($this->table),
            'state' => $reader->readStateFile($this->table, 'state.json'),
            'rolls' => $reader->readStateFile($this->table, 'rolls.json'),
            'recentEvents' => $recentEvents,
        ])->layout('layouts.admin', [
            'title' => $this->table->name,
            'heading' => $this->table->name,
        ]);
    }
}
