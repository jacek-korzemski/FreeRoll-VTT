@props(['used', 'limit', 'label' => null])

@php
    $used = max(0, (int) $used);
    $limit = max(0, (int) $limit);
    $pct = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0;
    $bar = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-blue-500');
@endphp

<div {{ $attributes }}>
    @if ($label)
        <p class="text-xs text-gray-400">{{ $label }}</p>
    @endif
    <p class="text-sm text-gray-300">
        {{ \App\Services\Admin\TelemetryAggregator::formatBytes($used) }}
        @if ($limit > 0)
            / {{ \App\Services\Admin\TelemetryAggregator::formatBytes($limit) }}
        @endif
    </p>
    @if ($limit > 0)
        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10">
            <div class="h-full rounded-full {{ $bar }}" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</div>
