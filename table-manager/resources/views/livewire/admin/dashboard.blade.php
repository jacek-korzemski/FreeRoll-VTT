<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Stoły</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['tableCount'] }}</p>
            <p class="mt-1 text-sm text-gray-400">online: {{ $stats['onlineTables'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Klienci online</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['onlineClients'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Logowania (24h)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['logins24h'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Unikalni klienci</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['uniqueClients'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Czas sesji (suma)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ \App\Services\Admin\TelemetryAggregator::formatDuration($stats['sessionSecondsTotal']) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Interakcje (24h)</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ $stats['interactions24h'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel/90 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Materiały</p>
            <p class="mt-1 text-3xl font-semibold text-white">{{ \App\Services\Admin\TelemetryAggregator::formatBytes($stats['uploadBytes']) }}</p>
            <p class="mt-1 text-sm text-gray-400">limit {{ (int) ($stats['tableUploadLimit'] / 1048576) }} MB / stół</p>
        </div>
    </div>
    <x-content-panel class="p-4">
        <p class="text-sm text-gray-400">Metryki obecności pochodzą z pollingu VTT (okno ~45 s bez requestu zamyka sesję). Stoły sprzed nowej paczki nie mają historii.</p>
    </x-content-panel>

    @if (session('admin_status'))
        <x-status-banner type="success">{{ session('admin_status') }}</x-status-banner>
    @endif
    @if (session('admin_error'))
        <x-status-banner type="danger">{{ session('admin_error') }}</x-status-banner>
    @endif

    <section class="rounded-xl border border-white/10 bg-vtt-panel/90 p-5 space-y-3">
        <div>
            <h2 class="text-sm font-semibold text-white">Baza danych</h2>
            <p class="mt-1 text-sm text-gray-400">
                Odpowiednik <code class="font-mono text-gray-300">php artisan migrate --force</code>
                oraz dociągnięcie brakujących kolumn (np. <code class="font-mono text-gray-300">color_template</code>), bez czyszczenia danych.
            </p>
        </div>

        <ul class="text-sm space-y-1">
            @foreach ($schemaStatus as $column => $ok)
                <li class="{{ $ok ? 'text-emerald-400' : 'text-amber-200' }}">
                    {{ $column }}: {{ $ok ? 'OK' : 'brak' }}
                </li>
            @endforeach
        </ul>

        @if (count($pendingMigrations) > 0)
            <p class="text-sm text-amber-200">Oczekujące migracje ({{ count($pendingMigrations) }}):</p>
            <ul class="text-xs font-mono text-gray-300 space-y-1">
                @foreach ($pendingMigrations as $migration)
                    <li>{{ $migration }}</li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400">Baza jest aktualna — nie ma oczekujących migracji.</p>
        @endif

        <x-warning-button type="button" wire:click="runMigrations" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="runMigrations">Aktualizuj bazę</span>
            <span wire:loading wire:target="runMigrations">Uruchamiam…</span>
        </x-warning-button>

        @if ($migrateOutput)
            <pre class="mt-2 max-h-48 overflow-auto rounded-md bg-black/40 p-3 text-xs text-gray-200 whitespace-pre-wrap">{{ $migrateOutput }}</pre>
        @endif
    </section>
</div>
