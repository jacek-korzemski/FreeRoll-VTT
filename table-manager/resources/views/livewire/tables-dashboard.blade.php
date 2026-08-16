<div class="space-y-8">
    @if (session('status'))
        <div class="rounded-lg border border-vtt-accent/40 bg-vtt-accent/15 px-4 py-3 text-sm text-white">
            {{ session('status') }}
        </div>
    @endif

    @if (! $sourceReady)
        <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            Brak paczki źródłowej VTT. Wgraj wynik <code class="font-mono">build.bat</code> do
            <code class="font-mono break-all">{{ $sourcePath }}</code>
            (pliki <code class="font-mono">index.php</code> i <code class="font-mono">assets/index.js</code>).
        </div>
    @endif

    <div class="flex items-end justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-white">Twoje stoły</h3>
            <p class="text-sm text-gray-400">{{ $count }} / {{ $max }} wykorzystane</p>
        </div>
    </div>

    @if ($tables->isEmpty())
        <p class="text-sm text-gray-400">Nie masz jeszcze żadnego stołu. Utwórz pierwszy poniżej.</p>
    @else
        <ul class="grid gap-4 lg:grid-cols-2">
            @foreach ($tables as $table)
                <li class="rounded-xl border border-white/10 bg-vtt-panel p-5 shadow-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-lg font-semibold text-white">{{ $table->name }}</h4>
                            <p class="mt-1 font-mono text-xs text-gray-400 break-all">{{ $table->publicPath() }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-white/10 px-2 py-1 text-xs uppercase tracking-wide text-gray-300">
                            {{ $table->language }}
                        </span>
                    </div>

                    <a href="{{ $table->publicUrl() }}" target="_blank" rel="noopener noreferrer"
                       class="mt-4 inline-flex items-center rounded-md bg-vtt-accent px-3 py-2 text-sm font-semibold text-white hover:bg-vtt-accent-hover">
                        Otwórz stół
                    </a>

                    @if ($editingId === $table->id)
                        <form wire:submit="saveSettings" class="mt-5 space-y-3 border-t border-white/10 pt-4">
                            <div>
                                <x-input-label for="edit_player_password_{{ $table->id }}" value="Hasło gracza" />
                                <x-text-input wire:model="edit_player_password" id="edit_player_password_{{ $table->id }}" type="text" class="mt-1 block w-full" required />
                                <x-input-error class="mt-1" :messages="$errors->get('edit_player_password')" />
                            </div>
                            <div>
                                <x-input-label for="edit_gm_password_{{ $table->id }}" value="Hasło Mistrza Gry" />
                                <x-text-input wire:model="edit_gm_password" id="edit_gm_password_{{ $table->id }}" type="text" class="mt-1 block w-full" required />
                                <x-input-error class="mt-1" :messages="$errors->get('edit_gm_password')" />
                            </div>
                            <div>
                                <x-input-label for="edit_language_{{ $table->id }}" value="Język" />
                                <select wire:model="edit_language" id="edit_language_{{ $table->id }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <option value="pl">Polski</option>
                                    <option value="en">English</option>
                                </select>
                                <x-input-error class="mt-1" :messages="$errors->get('edit_language')" />
                            </div>
                            <div class="flex gap-2">
                                <x-primary-button>Zapisz</x-primary-button>
                                <x-secondary-button type="button" wire:click="cancelEdit">Anuluj</x-secondary-button>
                            </div>
                        </form>
                    @elseif ($confirmingDeleteId === $table->id)
                        <div class="mt-5 space-y-3 border-t border-white/10 pt-4">
                            <p class="text-sm text-red-300">Na pewno usunąć ten stół? Mapa, stan sesji i pliki znikną bezpowrotnie.</p>
                            <div class="flex gap-2">
                                <x-danger-button type="button" wire:click="deleteTable">Usuń stół</x-danger-button>
                                <x-secondary-button type="button" wire:click="$set('confirmingDeleteId', null)">Anuluj</x-secondary-button>
                            </div>
                        </div>
                    @else
                        <div class="mt-5 space-y-2 border-t border-white/10 pt-4 text-sm">
                            <p><span class="text-gray-400">Hasło gracza:</span> <span class="font-mono text-gray-200">{{ $table->player_password }}</span></p>
                            <p><span class="text-gray-400">Hasło MG:</span> <span class="font-mono text-gray-200">{{ $table->gm_password }}</span></p>
                            <div class="flex flex-wrap gap-2 pt-2">
                                <x-secondary-button type="button" wire:click="startEdit({{ $table->id }})">Zmień hasła / język</x-secondary-button>
                                <x-danger-button type="button" wire:click="confirmDelete({{ $table->id }})">Usuń</x-danger-button>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <section class="rounded-xl border border-white/10 bg-vtt-panel p-5 shadow-lg">
        <h3 class="text-lg font-semibold text-white">Nowy stół</h3>
        @if (! $canCreate)
            <p class="mt-2 text-sm text-gray-400">Osiągnięto limit {{ $max }} stołów. Usuń jeden, żeby dodać kolejny.</p>
        @else
            <form wire:submit="createTable" class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Nazwa stołu" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" required :disabled="! $sourceReady" />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="player_password" value="Hasło gracza" />
                    <x-text-input wire:model="player_password" id="player_password" type="text" class="mt-1 block w-full" required :disabled="! $sourceReady" />
                    <x-input-error class="mt-1" :messages="$errors->get('player_password')" />
                </div>
                <div>
                    <x-input-label for="gm_password" value="Hasło Mistrza Gry" />
                    <x-text-input wire:model="gm_password" id="gm_password" type="text" class="mt-1 block w-full" required :disabled="! $sourceReady" />
                    <x-input-error class="mt-1" :messages="$errors->get('gm_password')" />
                </div>
                <div>
                    <x-input-label for="language" value="Język interfejsu stołu" />
                    <select wire:model="language" id="language" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $sourceReady)>
                        <option value="pl">Polski</option>
                        <option value="en">English</option>
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('language')" />
                </div>
                <div class="flex items-end">
                    <x-primary-button :disabled="! $sourceReady">Utwórz stół</x-primary-button>
                </div>
            </form>
        @endif
    </section>
</div>
