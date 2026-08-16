<?php

namespace App\Livewire\Admin;

use App\Services\Admin\TelemetryAggregator;
use Livewire\Component;

class TablesIndex extends Component
{
    public function render(TelemetryAggregator $aggregator)
    {
        return view('livewire.admin.tables-index', [
            'rows' => $aggregator->tableRows(),
        ])->layout('layouts.admin', [
            'title' => 'Stoły',
            'heading' => 'Stoły VTT',
        ]);
    }
}
