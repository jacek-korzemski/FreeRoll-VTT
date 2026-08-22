<?php

namespace App\Livewire\Admin;

use App\Services\Admin\TelemetryAggregator;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    public ?string $migrateOutput = null;

    public function runMigrations(): void
    {
        if (session('admin_authenticated') !== true) {
            abort(403);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            $this->migrateOutput = $output !== '' ? $output : 'Brak nowych migracji.';
            session()->flash('admin_status', 'Migracje zostały uruchomione.');
        } catch (Throwable $e) {
            $this->migrateOutput = $e->getMessage();
            session()->flash('admin_error', 'Migracje nie powiodły się.');
        }
    }

    public function render(TelemetryAggregator $aggregator)
    {
        return view('livewire.admin.dashboard', [
            'stats' => $aggregator->overview(),
            'pendingMigrations' => $this->pendingMigrationNames(),
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
