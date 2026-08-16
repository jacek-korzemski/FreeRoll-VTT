<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'FreeRoll Table Manager') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans bg-vtt-bg text-gray-100">
        <div class="min-h-screen flex flex-col">
            <header class="flex items-center justify-between px-6 py-5 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9 fill-current text-vtt-accent" />
                    <span class="font-semibold tracking-wide">FreeRoll Table Manager</span>
                </div>
                <livewire:welcome.navigation />
            </header>

            <main class="flex-1 flex items-center justify-center px-6 py-16">
                <div class="max-w-xl text-center">
                    <p class="text-5xl mb-6">🎲</p>
                    <h1 class="text-4xl font-semibold text-white">Twoje stoły VTT w jednym miejscu</h1>
                    <p class="mt-4 text-gray-400">
                        Zarejestruj się, załóż do trzech stołów FreeRoll i graj pod adresem
                        <code class="text-vtt-accent">/vtt/user/twoja-nazwa/…</code>
                    </p>
                    <div class="mt-8 flex justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-vtt-accent px-5 py-2.5 font-semibold text-white hover:bg-vtt-accent-hover">Przejdź do stołów</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-md bg-vtt-accent px-5 py-2.5 font-semibold text-white hover:bg-vtt-accent-hover">Załóż konto</a>
                            <a href="{{ route('login') }}" class="rounded-md border border-white/20 px-5 py-2.5 font-semibold text-white hover:bg-white/5">Zaloguj się</a>
                        @endauth
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
