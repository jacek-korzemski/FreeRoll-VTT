<?php

namespace App\Livewire\Admin;

use App\Services\Admin\SchemaRepair;
use App\Services\Admin\TelemetryAggregator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    public ?string $migrateOutput = null;

    public function runMigrations(SchemaRepair $repair): void
    {
        if (session('admin_authenticated') !== true) {
            abort(403);
        }

        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations',
            ]);
            $output = trim(Artisan::output());
            $notes = $repair->ensure();

            $parts = array_filter([
                $output !== '' ? $output : 'Brak nowych plików migracji.',
                ...$notes,
            ]);
            $this->migrateOutput = implode("\n", $parts);

            $columnOk = Schema::hasTable('vtt_tables')
                && Schema::hasColumn('vtt_tables', 'color_template');

            if ($columnOk) {
                session()->flash('admin_status', 'Baza zaktualizowana. Kolumna color_template jest na miejscu.');
            } else {
                session()->flash('admin_error', 'Migracje się wykonały, ale kolumna color_template nadal nie istnieje. Wgraj plik migracji i spróbuj ponownie.');
            }
        } catch (Throwable $e) {
            $this->migrateOutput = $e->getMessage();
            session()->flash('admin_error', 'Aktualizacja bazy nie powiodła się.');
        }
    }

    public function render(TelemetryAggregator $aggregator, SchemaRepair $repair)
    {
        return view('livewire.admin.dashboard', [
            'stats' => $aggregator->overview(),
            'pendingMigrations' => $this->pendingMigrationNames(),
            'schemaStatus' => $repair->columnStatus(),
        ])->layout('layouts.admin', [
            'title' => 'Przegląd',
            'heading' => 'Przegląd',
        ]);
    }

    /**
     * @return list<string>
     */
    private function pendingMigrationNames(): array
    {
        $migrator = app('migrator');
        $paths = array_values(array_unique(array_merge(
            $migrator->paths(),
            [database_path('migrations')],
        )));

        $files = $migrator->getMigrationFiles($paths);
        if (! $migrator->repositoryExists()) {
            return array_values(array_keys($files));
        }

        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        return array_values($pending);
    }
}
