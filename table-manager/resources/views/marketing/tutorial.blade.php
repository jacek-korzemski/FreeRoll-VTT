@php
    $meta = \App\Support\MarketingCopy::tutorialMeta();
    $steps = \App\Support\MarketingCopy::steps();
@endphp

<x-marketing-layout current="tutorial">
    <x-slot:meta>
        <x-marketing.meta
            :title="$meta['title']"
            :description="$meta['description']"
            :canonical="route('tutorial')"
            :schema="\App\Support\MarketingCopy::tutorialSchema()"
        />
    </x-slot:meta>

    <div class="rounded-2xl bg-vtt-panel/95 p-6 sm:p-8">
    <nav aria-label="Okruszki" class="text-sm text-gray-200">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" class="underline decoration-white/30 underline-offset-4">Strona główna</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Jak grać</li>
        </ol>
    </nav>

    <h1 class="mt-6 text-4xl font-semibold leading-tight text-white sm:text-5xl">Jak zagrać na darmowym stole VTT</h1>
    <p class="mt-5 max-w-3xl text-lg leading-relaxed text-gray-200">
        Krótka ścieżka od pustego konta do sesji. Każdy krok odpowiada temu, co naprawdę widać w FreeRoll: najpierw panel stołów, potem sam stół.
    </p>
    </div>

    <nav aria-label="Kroki tutorialu" class="mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5">
        <ol class="grid gap-2 sm:grid-cols-2">
            @foreach ($steps as $index => $step)
                <li>
                    <a href="#{{ $step['id'] }}" class="inline-flex min-h-11 items-center text-gray-100 underline decoration-white/30 underline-offset-4">
                        {{ $index + 1 }}. {{ $step['title'] }}
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    @foreach ($steps as $index => $step)
        <article id="{{ $step['id'] }}" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8" aria-labelledby="krok-{{ $step['id'] }}">
            <h2 id="krok-{{ $step['id'] }}" class="text-3xl font-semibold text-white">
                {{ $index + 1 }}. {{ $step['title'] }}
            </h2>
            @foreach ($step['paragraphs'] as $paragraph)
                <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">{{ $paragraph }}</p>
            @endforeach
            @if ($step['id'] === 'panel')
                <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
                    Kartę postaci składa się z szablonu.
                    <a href="{{ route('sheets') }}" class="font-semibold text-white underline decoration-white/40 underline-offset-4">Jak zrobić kartę postaci</a>
                    opisuje edytor MG i pliki HTML.
                </p>
            @endif
            <div class="mt-6">
                <x-marketing.shot :shot="$step['image']" />
            </div>
        </article>
    @endforeach

    <section class="mt-20 rounded-xl border border-white/10 bg-vtt-panel/95 p-6 sm:p-10" aria-labelledby="po-tutorialu">
        <h2 id="po-tutorialu" class="text-3xl font-semibold text-white">Możesz usiąść do stołu</h2>
        <p class="mt-3 max-w-3xl text-lg text-gray-200">
            Konto zakładasz raz. Kolejne sesje to nowe stoły albo ten sam link, który drużyna już zna.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                    Twoje stoły
                </a>
            @else
                <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                    Załóż konto
                </a>
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-md border border-white/30 px-5 font-semibold text-white hover:bg-white/10">
                    Zaloguj się
                </a>
            @endauth
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center rounded-md px-5 font-semibold text-white underline decoration-white/40 underline-offset-4">
                Wróć na stronę główną
            </a>
        </div>
    </section>
</x-marketing-layout>
