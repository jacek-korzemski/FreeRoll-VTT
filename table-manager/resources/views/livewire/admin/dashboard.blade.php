<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Stoły</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['tableCount'] }}</p>
            <p class="mt-1 text-sm text-gray-400">online: {{ $stats['onlineTables'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Klienci online</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['onlineClients'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Logowania (24h)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['logins24h'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Unikalni klienci</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['uniqueClients'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Czas sesji (suma)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ \App\Services\Admin\TelemetryAggregator::formatDuration($stats['sessionSecondsTotal']) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Interakcje (24h)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['interactions24h'] }}</p>
        </div>
    </div>
    <p class="text-sm text-gray-500">Metryki obecności pochodzą z pollingu VTT (okno ~45 s bez requestu zamyka sesję). Stoły sprzed nowej paczki nie mają historii.</p>
</div>
