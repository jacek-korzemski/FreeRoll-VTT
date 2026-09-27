@php
    $meta = \App\Support\MarketingCopy::homeMeta();
    $hero = \App\Support\MarketingCopy::heroImage();
@endphp

<x-marketing-layout current="home">
    <x-slot:meta>
        <x-marketing.meta
            :title="$meta['title']"
            :description="$meta['description']"
            :canonical="route('home')"
            :schema="\App\Support\MarketingCopy::homeSchema()"
        />
    </x-slot:meta>

    <section class="grid items-center gap-10 lg:grid-cols-2">
        <div class="rounded-2xl bg-vtt-panel/95 p-6 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-300">FreeRoll VTT</p>
            <h1 class="mt-3 text-4xl font-semibold leading-tight text-white sm:text-5xl">
                Darmowy stół VTT do gier RPG online
            </h1>
            <p class="mt-5 text-lg leading-relaxed text-gray-200">
                FreeRoll to wirtualny stół na sesję RPG. Mistrz Gry zakłada pokój, wysyła drużynie link i gracie na wspólnej mapie. Wystarczy przeglądarka: bez instalacji u graczy i bez osobnego programu.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                        Twoje stoły
                    </a>
                @else
                    <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                        Załóż darmowy stół
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-md border border-white/30 px-5 font-semibold text-white hover:bg-white/10">
                        Zaloguj się
                    </a>
                @endauth
                <a href="{{ route('tutorial') }}" class="inline-flex min-h-11 items-center rounded-md px-5 font-semibold text-white underline decoration-white/40 underline-offset-4">
                    Jak to działa
                </a>
            </div>
        </div>
        <x-marketing.shot :shot="$hero" :priority="true" />
    </section>

    <section class="mt-20" aria-labelledby="start">
        <h2 id="start" class="text-3xl font-semibold text-white">Od konta do pierwszej sesji</h2>
        <p class="mt-3 max-w-3xl text-lg text-gray-200">
            Trzy kroki. Potem drużyna siedzi przy tym samym stole, każde na swoim ekranie.
        </p>
        <ol class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach (\App\Support\MarketingCopy::startSteps() as $index => $step)
                <li class="rounded-xl border border-white/10 bg-vtt-panel/95 p-5">
                    <p class="text-sm font-semibold text-blue-300">Krok {{ $index + 1 }}</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">
                        <a href="{{ $step['href'] }}" class="underline decoration-white/30 underline-offset-4">{{ $step['title'] }}</a>
                    </h3>
                    <p class="mt-3 leading-relaxed text-gray-200">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-20" aria-labelledby="funkcje">
        <h2 id="funkcje" class="text-3xl font-semibold text-white">Co jest na stole</h2>
        <p class="mt-3 max-w-3xl text-lg text-gray-200">
            Zestaw pod zwykłą sesję przy stole: mapa, która jest dla wszystkich ta sama, i narzędzia, których używasz w trakcie gry.
        </p>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach (\App\Support\MarketingCopy::features() as $feature)
                <li class="rounded-xl border border-white/10 bg-vtt-panel/95 p-5">
                    <h3 class="text-xl font-semibold text-white">
                        <a href="{{ $feature['href'] }}" class="underline decoration-white/30 underline-offset-4">{{ $feature['title'] }}</a>
                    </h3>
                    <p class="mt-3 leading-relaxed text-gray-200">{{ $feature['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mt-20" aria-labelledby="pytania">
        <h2 id="pytania" class="text-3xl font-semibold text-white">Częste pytania</h2>
        <div class="mt-8 space-y-3">
            @foreach (\App\Support\MarketingCopy::faqs() as $faq)
                <details class="rounded-xl border border-white/10 bg-vtt-panel/95 px-5 py-2">
                    <summary class="cursor-pointer list-none py-3 text-lg font-semibold text-white [&::-webkit-details-marker]:hidden">
                        {{ $faq['q'] }}
                    </summary>
                    <p class="pb-4 leading-relaxed text-gray-200">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="mt-20 rounded-xl border border-white/10 bg-vtt-panel/95 p-6 sm:p-10" aria-labelledby="zaczynaj">
        <h2 id="zaczynaj" class="text-3xl font-semibold text-white">Postaw drużynę przy stole</h2>
        <p class="mt-3 max-w-3xl text-lg text-gray-200">
            Konto jest darmowe. Stół otwiera się w przeglądarce, a kod projektu możesz przeczytać na GitHubie.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                    Przejdź do stołów
                </a>
            @else
                <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                    Załóż konto
                </a>
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-md border border-white/30 px-5 font-semibold text-white hover:bg-white/10">
                    Zaloguj się
                </a>
            @endauth
            <a href="{{ \App\Support\MarketingCopy::GITHUB }}" class="inline-flex min-h-11 items-center rounded-md px-5 font-semibold text-white underline decoration-white/40 underline-offset-4" rel="noopener noreferrer">
                Zobacz kod na GitHubie
            </a>
        </div>
    </section>
</x-marketing-layout>
