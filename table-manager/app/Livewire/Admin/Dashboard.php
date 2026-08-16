<?php

namespace App\Livewire\Admin;

use App\Services\Admin\TelemetryAggregator;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(TelemetryAggregator $aggregator)
    {
        return view('livewire.admin.dashboard', [
            'stats' => $aggregator->overview(),
        ])->layout('layouts.admin', [
            'title' => 'Przegląd',
            'heading' => 'Przegląd',
        ]);
    }
}
