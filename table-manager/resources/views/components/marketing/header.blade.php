@props(['current' => 'home'])

@php
    $link = 'inline-flex min-h-11 items-center rounded-md px-3 text-sm font-semibold text-gray-100 hover:text-white';
    $github = \App\Support\MarketingCopy::GITHUB;
@endphp

<header class="border-b border-white/10 bg-vtt-panel/90">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-3 rounded-md">
            <img src="{{ asset('images/logo.png') }}" alt="" width="40" height="40" class="h-10 w-10 object-contain">
            <span class="text-base font-semibold tracking-wide text-white">FreeRoll</span>
        </a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Główne">
            <a href="{{ route('tutorial') }}" @class([$link, 'text-white' => $current === 'tutorial']) @if ($current === 'tutorial') aria-current="page" @endif>
                Jak grać
            </a>
            <a href="{{ $github }}" class="{{ $link }}" rel="noopener noreferrer">GitHub</a>
            @auth
                <a href="{{ route('dashboard') }}" class="ms-2 inline-flex min-h-11 items-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-600">
                    Twoje stoły
                </a>
            @else
                <a href="{{ route('login') }}" class="{{ $link }}">Zaloguj się</a>
                <a href="{{ route('register') }}" class="ms-1 inline-flex min-h-11 items-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-600">
                    Załóż konto
                </a>
            @endauth
        </nav>

        <details class="relative md:hidden">
            <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-md text-white [&::-webkit-details-marker]:hidden">
                <span class="sr-only">Menu</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </summary>
            <nav class="absolute right-0 z-20 mt-2 w-56 rounded-xl border border-white/10 bg-vtt-panel p-2 shadow-xl" aria-label="Główne">
                <a href="{{ route('tutorial') }}" class="{{ $link }} w-full" @if ($current === 'tutorial') aria-current="page" @endif>Jak grać</a>
                <a href="{{ $github }}" class="{{ $link }} w-full" rel="noopener noreferrer">GitHub</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="{{ $link }} w-full">Twoje stoły</a>
                @else
                    <a href="{{ route('login') }}" class="{{ $link }} w-full">Zaloguj się</a>
                    <a href="{{ route('register') }}" class="{{ $link }} w-full">Załóż konto</a>
                @endauth
            </nav>
        </details>
    </div>
</header>
