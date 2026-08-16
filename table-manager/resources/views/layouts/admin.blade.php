<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? 'Admin' }} — FreeRoll</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-100">
        <div class="min-h-screen bg-vtt-bg flex">
            <aside class="w-56 shrink-0 border-r border-white/10 bg-vtt-panel flex flex-col">
                <div class="px-4 py-5 border-b border-white/10">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Panel</p>
                    <p class="mt-1 font-semibold text-white">Admin VTT</p>
                </div>
                <nav class="flex-1 px-2 py-4 space-y-1 text-sm">
                    <a href="{{ route('admin.dashboard') }}"
                       class="block rounded-md px-3 py-2 {{ request()->routeIs('admin.dashboard') ? 'bg-vtt-accent/20 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                        Przegląd
                    </a>
                    <a href="{{ route('admin.tables') }}"
                       class="block rounded-md px-3 py-2 {{ request()->routeIs('admin.tables') || request()->routeIs('admin.tables.show') ? 'bg-vtt-accent/20 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                        Stoły
                    </a>
                    <a href="{{ route('admin.files') }}"
                       class="block rounded-md px-3 py-2 {{ request()->routeIs('admin.files') ? 'bg-vtt-accent/20 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                        Pliki
                    </a>
                    <a href="{{ route('admin.analytics') }}"
                       class="block rounded-md px-3 py-2 {{ request()->routeIs('admin.analytics') ? 'bg-vtt-accent/20 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                        Analityka
                    </a>
                </nav>
                <form method="POST" action="{{ route('admin.logout') }}" class="p-3 border-t border-white/10">
                    @csrf
                    <button type="submit" class="w-full rounded-md px-3 py-2 text-sm text-gray-400 hover:bg-white/5 hover:text-white text-left">
                        Wyloguj
                    </button>
                </form>
            </aside>
            <div class="flex-1 min-w-0">
                <header class="border-b border-white/10 bg-vtt-panel/80 px-6 py-4">
                    <h1 class="text-lg font-semibold text-white">{{ $heading ?? $title ?? 'Admin' }}</h1>
                </header>
                <main class="px-6 py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
