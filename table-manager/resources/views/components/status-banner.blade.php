@props(['type' => 'info'])

@php
    $styles = [
        'success' => 'border-emerald-500/50 bg-vtt-panel/90 text-emerald-100',
        'warning' => 'border-amber-500/50 bg-vtt-panel/90 text-amber-100',
        'danger' => 'border-red-500/50 bg-vtt-panel/90 text-red-200',
        'info' => 'border-blue-500/50 bg-vtt-panel/90 text-blue-100',
    ][$type] ?? 'border-blue-500/50 bg-vtt-panel/90 text-blue-100';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm '.$styles]) }}>
    {{ $slot }}
</div>
