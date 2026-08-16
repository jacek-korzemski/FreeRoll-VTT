<?php

namespace App\Livewire\Admin;

use App\Services\Admin\TelemetryAggregator;
use Livewire\Component;

class Analytics extends Component
{
    public function render(TelemetryAggregator $aggregator)
    {
        return view('livewire.admin.analytics', [
            'data' => $aggregator->analytics(),
        ])->layout('layouts.admin', [
            'title' => 'Analityka',
            'heading' => 'Analityka stołów',
        ]);
    }
}
