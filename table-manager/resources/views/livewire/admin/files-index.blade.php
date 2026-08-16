<div class="space-y-4">
    @if (session('admin_status'))
        <div class="rounded-lg border border-vtt-accent/40 bg-vtt-accent/15 px-4 py-3 text-sm text-white">{{ session('admin_status') }}</div>
    @endif
    @if (session('admin_error'))
        <div class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('admin_error') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-white/10 bg-vtt-panel">
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-400 border-b border-white/10">
                <tr>
                    <th class="px-4 py-3 font-medium">Podgląd</th>
                    <th class="px-4 py-3 font-medium">Plik</th>
                    <th class="px-4 py-3 font-medium">Stół</th>
                    <th class="px-4 py-3 font-medium">Owner</th>
                    <th class="px-4 py-3 font-medium">Rozmiar</th>
                    <th class="px-4 py-3 font-medium">Data</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($files as $file)
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-2">
                            @if ($file['isImage'])
                                <img src="{{ route('admin.download.asset', $file['tableId']) }}?path={{ urlencode($file['relative']) }}" alt="" class="h-10 w-10 object-cover rounded">
                            @else
                                <span class="text-xs uppercase text-gray-500">{{ $file['type'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-xs text-gray-200 break-all">{{ $file['relative'] }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.tables.show', $file['tableId']) }}" class="text-vtt-accent hover:text-vtt-accent-hover">{{ $file['tableName'] }}</a>
                        </td>
                        <td class="px-4 py-2 text-gray-400">{{ $file['owner'] }}</td>
                        <td class="px-4 py-2 text-gray-400 whitespace-nowrap">{{ \App\Services\Admin\TelemetryAggregator::formatBytes($file['size']) }}</td>
                        <td class="px-4 py-2 text-gray-400 whitespace-nowrap">{{ date('Y-m-d H:i', $file['mtime']) }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <a href="{{ route('admin.download.asset', $file['tableId']) }}?path={{ urlencode($file['relative']) }}" class="text-vtt-accent hover:text-vtt-accent-hover">Pobierz</a>
                            <button type="button" class="ml-2 text-red-400 hover:text-red-300" wire:click="deleteFile({{ $file['tableId'] }}, '{{ $file['relative'] }}')" wire:confirm="Usunąć ten plik?">Usuń</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Brak plików.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
