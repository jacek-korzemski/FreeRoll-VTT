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
            (pliki <code class="font-mono">index.php</code>, <code class="font-mono">assets/index.js</code> i <code class="font-mono">backend/vendor/autoload.php</code>).
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
                            ·
                            {{ $table->color_template }}
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
                            <div>
                                <x-input-label for="edit_color_template_{{ $table->id }}" value="Szablon kolorystyczny" />
                                <div class="mt-1 flex gap-2">
                                    <select wire:model="edit_color_template" id="edit_color_template_{{ $table->id }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                        @foreach ($colorThemes as $theme)
                                            <option value="{{ $theme['id'] }}">{{ $theme['name']['pl'] }} ({{ $theme['id'] }})</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="openThemePreview('edit')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-white/15 bg-white/5 text-gray-300 hover:bg-white/10 hover:text-white" title="Podgląd szablonów" aria-label="Podgląd szablonów">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                        </svg>
                                    </button>
                                </div>
                                <x-input-error class="mt-1" :messages="$errors->get('edit_color_template')" />
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
                                <x-secondary-button type="button" wire:click="startEdit({{ $table->id }})">Zmień ustawienia</x-secondary-button>
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
                <div>
                    <x-input-label for="color_template" value="Szablon kolorystyczny" />
                    <div class="mt-1 flex gap-2">
                        <select wire:model="color_template" id="color_template" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $sourceReady)>
                            @foreach ($colorThemes as $theme)
                                <option value="{{ $theme['id'] }}">{{ $theme['name']['pl'] }} ({{ $theme['id'] }})</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="openThemePreview('create')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-white/15 bg-white/5 text-gray-300 hover:bg-white/10 hover:text-white disabled:cursor-not-allowed disabled:opacity-50" title="Podgląd szablonów" aria-label="Podgląd szablonów" @disabled(! $sourceReady)>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error class="mt-1" :messages="$errors->get('color_template')" />
                </div>
                <div class="flex items-end">
                    <x-primary-button :disabled="! $sourceReady">Utwórz stół</x-primary-button>
                </div>
            </form>
        @endif
    </section>

    @if ($showThemePreview)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            wire:keydown.escape.window="closeThemePreview"
            role="dialog"
            aria-modal="true"
            aria-labelledby="theme-preview-title"
        >
            <div class="absolute inset-0 bg-black/70" wire:click="closeThemePreview"></div>
            <div class="relative z-10 flex h-auto max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-white/10 bg-vtt-panel shadow-2xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                    <div>
                        <h3 id="theme-preview-title" class="text-lg font-semibold text-white">Szablony kolorystyczne</h3>
                        <p class="mt-1 text-sm text-gray-400">Kliknij wariant, żeby go wybrać.</p>
                    </div>
                    <button type="button" wire:click="closeThemePreview" class="rounded-md p-1 text-gray-400 hover:bg-white/10 hover:text-white" aria-label="Zamknij">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($colorThemes as $theme)
                            @php
                                $isSelected = $themePreviewTarget === 'edit'
                                    ? $edit_color_template === $theme['id']
                                    : $color_template === $theme['id'];
                            @endphp
                            <li>
                                <button
                                    type="button"
                                    wire:click="pickColorTemplate('{{ $theme['id'] }}')"
                                    class="group w-full overflow-hidden rounded-lg border text-left transition {{ $isSelected ? 'border-vtt-accent ring-1 ring-vtt-accent' : 'border-white/10 hover:border-white/25' }}"
                                >
                                    @if (! empty($theme['preview']))
                                        <img src="{{ $theme['preview'] }}" alt="{{ $theme['name']['pl'] }}" class="aspect-video w-full object-cover object-top bg-black">
                                    @else
                                        <div class="flex aspect-video items-center justify-center bg-black/40 text-sm text-gray-500">Brak podglądu</div>
                                    @endif
                                    <div class="flex items-center justify-between gap-2 px-3 py-2">
                                        <span class="text-sm font-medium text-white">{{ $theme['name']['pl'] }}</span>
                                        <span class="font-mono text-xs uppercase text-gray-400">{{ $theme['id'] }}</span>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
