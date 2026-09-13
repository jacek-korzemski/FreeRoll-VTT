<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'FreeRoll Table Manager') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans text-gray-100">
        <x-app-background />
        <div class="relative z-10 min-h-screen flex flex-col">
            <header class="flex items-center justify-between px-6 py-5 border-b border-white/10 bg-vtt-panel">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-auto" />
                    <span class="font-semibold tracking-wide">FreeRoll Table Manager</span>
                </div>
                <livewire:welcome.navigation />
            </header>

            <main class="flex-1 flex items-center justify-center px-6 py-16">
                <x-content-panel class="max-w-xl p-8 sm:p-10 text-center">
                    <p class="mb-6 flex justify-center">
                        <x-application-logo class="h-28 w-auto" />
                    </p>
                    <h1 class="text-4xl font-semibold text-white">Twoje stoły VTT w jednym miejscu</h1>
                    <p class="mt-4 text-gray-400">
                        Zarejestruj się, załóż do trzech stołów FreeRoll i graj pod adresem
                        <code class="text-vtt-accent">/vtt/user/twoja-nazwa/…</code>
                    </p>
                    <div class="mt-8 flex justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-500">Przejdź do stołów</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-500">Załóż konto</a>
                            <a href="{{ route('login') }}" class="rounded-md border border-white/20 px-5 py-2.5 font-semibold text-white hover:bg-white/5">Zaloguj się</a>
                        @endauth
                    </div>
                </x-content-panel>
            </main>
        </div>
    </body>
</html>
