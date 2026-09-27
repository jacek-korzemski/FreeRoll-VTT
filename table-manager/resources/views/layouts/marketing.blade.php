<!DOCTYPE html>
<html lang="pl" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css'])
        {{ $meta }}
    </head>
    <body class="marketing bg-vtt-bg font-sans text-gray-100 antialiased">
        <x-app-background />
        <a href="#tresc" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-blue-700 focus:px-4 focus:py-3 focus:text-white">
            Przejdź do treści
        </a>
        <div class="relative z-10 flex min-h-screen flex-col">
            <x-marketing.header :current="$current ?? 'home'" />
            <main id="tresc" tabindex="-1" class="mx-auto w-full max-w-6xl flex-1 px-4 py-12 outline-none sm:px-6 sm:py-16">
                {{ $slot }}
            </main>
            <x-marketing.footer />
        </div>
    </body>
</html>
