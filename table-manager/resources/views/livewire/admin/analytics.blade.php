<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Unikalni klienci</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ $data['uniqueClients'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Logowania gracz / MG / fail</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ $data['logins']['player'] }} / {{ $data['logins']['gm'] }} / {{ $data['logins']['fail'] }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-vtt-panel p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400">Suma czasu sesji</p>
            <p class="mt-1 text-2xl font-semibold text-white">{{ \App\Services\Admin\TelemetryAggregator::formatDuration($data['sessionSecondsTotal']) }}</p>
        </div>
    </div>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Interakcje w ostatnich 24h (UTC)</h2>
        <div class="mt-4 flex items-end gap-1 h-28">
            @foreach ($data['hourly24'] as $hour => $count)
                @php $pct = $data['hourlyMax'] > 0 ? (100 * $count / $data['hourlyMax']) : 0; @endphp
                <div class="flex-1 flex flex-col justify-end items-center h-full" title="{{ $hour }}:00 — {{ $count }}">
                    <div class="w-full rounded-t bg-vtt-accent/80" style="height: {{ $count > 0 ? max(8, $pct) : 0 }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-gray-500">
            <span>0</span><span>6</span><span>12</span><span>18</span><span>23</span>
        </div>
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Najczęstsze akcje</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($data['topActions'] as $action => $count)
                <li class="flex justify-between text-gray-300">
                    <span class="font-mono text-xs">{{ $action }}</span>
                    <span>{{ $count }}</span>
                </li>
            @empty
                <li class="text-gray-500">Brak interakcji.</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5">
        <h2 class="text-sm font-semibold text-white">Sesje (od — do)</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-400 border-b border-white/10">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Stół</th>
                        <th class="py-2 pr-4 font-medium">Od</th>
                        <th class="py-2 pr-4 font-medium">Do</th>
                        <th class="py-2 pr-4 font-medium">Czas</th>
                        <th class="py-2 pr-4 font-medium">Peak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['sessions'] as $session)
                        <tr class="border-t border-white/5 text-gray-300">
                            <td class="py-2 pr-4">
                                <a href="{{ route('admin.tables.show', $session['tableId']) }}" class="text-vtt-accent hover:text-vtt-accent-hover">{{ $session['tableName'] }}</a>
                                <div class="text-xs text-gray-500">{{ $session['owner'] }}</div>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ date('Y-m-d H:i', $session['startedAt'] ?? 0) }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                @if (!empty($session['open']))
                                    <span class="text-emerald-400">trwa</span>
                                @else
                                    {{ date('Y-m-d H:i', $session['endedAt'] ?? 0) }}
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ \App\Services\Admin\TelemetryAggregator::formatDuration($session['durationSec'] ?? 0) }}</td>
                            <td class="py-2 pr-4">{{ $session['peakClients'] ?? 1 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-gray-500">Brak sesji.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
