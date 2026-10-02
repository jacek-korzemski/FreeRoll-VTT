<?php

namespace App\Livewire\Admin;

use App\Models\VttTable;
use App\Services\Admin\TableDiskReader;
use App\Services\StorageQuota;
use App\Services\TableProvisioner;
use Livewire\Component;

class TableShow extends Component
{
    public VttTable $table;

    public bool $confirmingDelete = false;

    public bool $confirmingReset = false;

    public string $ownerMaxTables = '';

    public string $uploadQuotaMb = '';

    public function mount(VttTable $table): void
    {
        $this->table = $table->load('user');
        $this->fillLimitFields();
    }

    public function saveLimits(TableProvisioner $provisioner): void
    {
        $this->ownerMaxTables = trim($this->ownerMaxTables);
        $this->uploadQuotaMb = trim($this->uploadQuotaMb);

        $validated = $this->validate([
            'ownerMaxTables' => ['nullable', 'integer', 'min:1', 'max:100'],
            'uploadQuotaMb' => ['nullable', 'integer', 'min:1', 'max:10240'],
        ], [
            'ownerMaxTables.integer' => 'Podaj liczbę stołów.',
            'ownerMaxTables.min' => 'Limit stołów musi wynosić co najmniej 1.',
            'ownerMaxTables.max' => 'Limit stołów może wynosić najwyżej 100.',
            'uploadQuotaMb.integer' => 'Podaj limit plików w MB.',
            'uploadQuotaMb.min' => 'Limit plików musi wynosić co najmniej 1 MB.',
            'uploadQuotaMb.max' => 'Limit plików może wynosić najwyżej 10240 MB.',
        ]);

        $user = $this->table->user;
        $user->max_tables = $validated['ownerMaxTables'] === null || $validated['ownerMaxTables'] === ''
            ? null
            : (int) $validated['ownerMaxTables'];
        $user->save();

        $this->table->upload_quota_mb = $validated['uploadQuotaMb'] === null || $validated['uploadQuotaMb'] === ''
            ? null
            : (int) $validated['uploadQuotaMb'];
        $this->table->save();

        $provisioner->writeEnv($this->table->load('user'));
        $this->table->refresh()->load('user');
        $this->fillLimitFields();
        session()->flash('admin_status', 'Limity zapisane.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function prepareForValidation($attributes)
    {
        foreach (['ownerMaxTables', 'uploadQuotaMb'] as $field) {
            if (! array_key_exists($field, $attributes) || ! is_string($attributes[$field])) {
                continue;
            }
            if (trim($attributes[$field]) === '') {
                $attributes[$field] = null;
            }
        }

        return $attributes;
    }

    private function fillLimitFields(): void
    {
        $this->table->loadMissing('user');
        $this->ownerMaxTables = $this->table->user->max_tables === null
            ? ''
            : (string) $this->table->user->max_tables;
        $this->uploadQuotaMb = $this->table->upload_quota_mb === null
            ? ''
            : (string) $this->table->upload_quota_mb;
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

    public function render(TableDiskReader $reader, StorageQuota $quota)
    {
        $telemetry = $reader->telemetry($this->table);
        $recentEvents = array_slice(array_reverse($telemetry['events']), 0, 40);

        return view('livewire.admin.table-show', [
            'telemetry' => $telemetry,
            'assets' => $reader->listAssets($this->table),
            'assetBytes' => $reader->assetUsageBytes($this->table),
            'tableUploadLimit' => $quota->tableLimitBytes($this->table),
            'defaultMaxTables' => (int) config('vtt.max_tables', 3),
            'defaultUploadMb' => (int) config('vtt.max_table_upload_mb', 50),
            'state' => $reader->readStateFile($this->table, 'state.json'),
            'rolls' => $reader->readStateFile($this->table, 'rolls.json'),
            'recentEvents' => $recentEvents,
        ])->layout('layouts.admin', [
            'title' => $this->table->name,
            'heading' => $this->table->name,
        ]);
    }
}
