<div class="space-y-4">
    @if (session('admin_status'))
        <div class="rounded-lg border border-vtt-accent/40 bg-vtt-accent/15 px-4 py-3 text-sm text-white">{{ session('admin_status') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-vtt-panel">
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-400 border-b border-white/10">
                <tr>
                    <th class="px-4 py-3 font-medium">Stół</th>
                    <th class="px-4 py-3 font-medium">Owner</th>
                    <th class="px-4 py-3 font-medium">Język</th>
                    <th class="px-4 py-3 font-medium">Utworzony</th>
                    <th class="px-4 py-3 font-medium">Ostatnia aktywność</th>
                    <th class="px-4 py-3 font-medium">Online</th>
                    <th class="px-4 py-3 font-medium">Hasło gracza</th>
                    <th class="px-4 py-3 font-medium">Hasło MG</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $table = $row['table']; @endphp
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-3 text-white font-medium">{{ $table->name }}</td>
                        <td class="px-4 py-3 text-gray-300">
                            {{ $table->user->username }}
                            <div class="text-xs text-gray-500">{{ $table->user->email }}</div>
                        </td>
                        <td class="px-4 py-3 uppercase text-gray-400">{{ $table->language }}</td>
                        <td class="px-4 py-3 text-gray-400 whitespace-nowrap">{{ $table->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-gray-400 whitespace-nowrap">
                            {{ $row['lastSeen'] ? date('Y-m-d H:i', $row['lastSeen']) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($row['onlineCount'] > 0)
                                <span class="text-emerald-400">{{ $row['onlineCount'] }}</span>
                            @else
                                <span class="text-gray-500">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-200">{{ $table->player_password }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-200">{{ $table->gm_password }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('admin.tables.show', $table) }}" class="text-vtt-accent hover:text-vtt-accent-hover">Szczegóły</a>
                            <a href="{{ $table->publicUrl() }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-gray-400 hover:text-white">Otwórz</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">Brak stołów.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
