<div class="space-y-6">
    @if (session('admin_status'))
        <div class="rounded-lg border border-vtt-accent/40 bg-vtt-accent/15 px-4 py-3 text-sm text-white">{{ session('admin_status') }}</div>
    @endif
    @if (session('admin_error'))
        <div class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('admin_error') }}</div>
    @endif

    <p class="text-sm text-gray-400">
        <a href="{{ route('admin.tables') }}" class="text-vtt-accent hover:text-vtt-accent-hover">← Stoły</a>
        <span class="mx-2">·</span>
        Owner: <span class="text-gray-200">{{ $table->user->username }}</span> ({{ $table->user->email }})
        <span class="mx-2">·</span>
        <a href="{{ $table->publicUrl() }}" target="_blank" rel="noopener noreferrer" class="text-vtt-accent hover:text-vtt-accent-hover">Otwórz stół</a>
    </p>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5 grid gap-4 sm:grid-cols-2">
        <div>
            <h2 class="text-sm font-semibold text-white">Hasła VTT</h2>
            <p class="mt-2 text-sm text-gray-400">Gracz: <span class="font-mono text-gray-200">{{ $table->player_password }}</span></p>
            <p class="mt-1 text-sm text-gray-400">MG: <span class="font-mono text-gray-200">{{ $table->gm_password }}</span></p>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">Obecność</h2>
            <p class="mt-2 text-sm text-gray-400">Online: {{ $telemetry['onlineCount'] }}</p>
            <p class="text-sm text-gray-400">Unikalni klienci: {{ $telemetry['uniqueClients'] }}</p>
            <p class="text-sm text-gray-400">Czas sesji: {{ \App\Services\Admin\TelemetryAggregator::formatDuration($telemetry['sessionSecondsTotal']) }}</p>
            <p class="text-sm text-gray-400">Logowania G/MG/fail: {{ $telemetry['logins']['player'] }} / {{ $telemetry['logins']['gm'] }} / {{ $telemetry['logins']['fail'] }}</p>
        </div>
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Sesje stołu</h2>
        <ul class="mt-3 space-y-1 text-sm text-gray-300">
            @if ($telemetry['openSession'])
                <li>
                    <span class="text-emerald-400">trwa</span>
                    {{ date('Y-m-d H:i', $telemetry['openSession']['startedAt'] ?? time()) }}
                    — {{ \App\Services\Admin\TelemetryAggregator::formatDuration($telemetry['openSession']['durationSec'] ?? 0) }},
                    peak {{ $telemetry['openSession']['peakClients'] ?? 1 }}
                </li>
            @endif
            @forelse ($telemetry['sessions'] as $session)
                <li>
                    {{ date('Y-m-d H:i', $session['startedAt'] ?? 0) }}
                    → {{ date('Y-m-d H:i', $session['endedAt'] ?? 0) }}
                    ({{ \App\Services\Admin\TelemetryAggregator::formatDuration($session['durationSec'] ?? 0) }},
                    peak {{ $session['peakClients'] ?? 1 }})
                </li>
            @empty
                @unless ($telemetry['openSession'])
                    <li class="text-gray-500">Brak zapisanych sesji.</li>
                @endunless
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-white">Stan gry</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.download.json', [$table, 'state']) }}" class="text-sm text-vtt-accent hover:text-vtt-accent-hover">Pobierz state.json</a>
                <a href="{{ route('admin.download.json', [$table, 'rolls']) }}" class="text-sm text-vtt-accent hover:text-vtt-accent-hover">Pobierz rolls.json</a>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">state.json {{ $state['exists'] ? 'mtime '.date('Y-m-d H:i', $state['mtime']) : 'brak' }} · rolls.json {{ $rolls['exists'] ? 'mtime '.date('Y-m-d H:i', $rolls['mtime']) : 'brak' }}</p>
        <pre class="mt-3 max-h-64 overflow-auto rounded-md bg-black/40 p-3 text-xs text-gray-300">{{ $state['raw'] !== '' ? $state['raw'] : '(pusty)' }}</pre>
        @if ($confirmingReset)
            <div class="mt-4 space-y-2">
                <p class="text-sm text-red-300">Zresetować stan mapy i kości? Pliki uploadów zostaną.</p>
                <x-danger-button type="button" wire:click="resetState">Potwierdź reset</x-danger-button>
                <x-secondary-button type="button" wire:click="$set('confirmingReset', false)">Anuluj</x-secondary-button>
            </div>
        @else
            <x-secondary-button type="button" class="mt-4" wire:click="$set('confirmingReset', true)">Resetuj stan gry</x-secondary-button>
        @endif
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Pliki stołu</h2>
        <ul class="mt-3 divide-y divide-white/5">
            @forelse ($assets as $asset)
                <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-gray-300 font-mono text-xs break-all">{{ $asset['relative'] }}</span>
                    <span class="text-gray-500">{{ \App\Services\Admin\TelemetryAggregator::formatBytes($asset['size']) }} · {{ date('Y-m-d H:i', $asset['mtime']) }}</span>
                    <span class="flex gap-2">
                        <a href="{{ route('admin.download.asset', $table) }}?path={{ urlencode($asset['relative']) }}" class="text-vtt-accent hover:text-vtt-accent-hover">Pobierz</a>
                        <button type="button" class="text-red-400 hover:text-red-300" wire:click="deleteFile('{{ $asset['relative'] }}')" wire:confirm="Usunąć ten plik?">Usuń</button>
                    </span>
                </li>
            @empty
                <li class="py-2 text-gray-500 text-sm">Brak wgranych plików.</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Ostatnie zdarzenia</h2>
        <ul class="mt-3 space-y-1 text-xs font-mono text-gray-400 max-h-64 overflow-auto">
            @forelse ($recentEvents as $event)
                <li>
                    {{ date('Y-m-d H:i:s', $event['ts'] ?? 0) }}
                    {{ $event['type'] ?? '?' }}
                    {{ $event['action'] ?? '' }}
                    {{ $event['role'] ?? '' }}
                    {{ $event['clientId'] ?? '' }}
                </li>
            @empty
                <li>Brak telemetrii (stół bez nowej paczki albo jeszcze nieużywany).</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl border border-red-500/30 bg-red-950/20 p-5">
        <h2 class="text-sm font-semibold text-red-200">Moderacja</h2>
        @if ($confirmingDelete)
            <p class="mt-2 text-sm text-red-300">Usunąć stół wraz z plikami i stanem? Tej operacji nie da się cofnąć.</p>
            <div class="mt-3 flex gap-2">
                <x-danger-button type="button" wire:click="deleteTable">Usuń stół</x-danger-button>
                <x-secondary-button type="button" wire:click="$set('confirmingDelete', false)">Anuluj</x-secondary-button>
            </div>
        @else
            <x-danger-button type="button" class="mt-3" wire:click="$set('confirmingDelete', true)">Usuń stół</x-danger-button>
        @endif
    </section>
</div>
