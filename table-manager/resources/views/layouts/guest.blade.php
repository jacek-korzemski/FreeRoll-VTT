<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'FreeRoll Table Manager') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-100 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-vtt-bg">
            <div class="text-center">
                <a href="/" wire:navigate class="inline-block">
                    <x-application-logo class="w-16 h-16 fill-current text-vtt-accent mx-auto" />
                </a>
                <p class="mt-3 text-sm tracking-wide text-gray-400">FreeRoll Table Manager</p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-vtt-panel shadow-md overflow-hidden sm:rounded-lg border border-white/10">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
