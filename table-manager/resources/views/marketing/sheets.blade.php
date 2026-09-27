@php
    $meta = \App\Support\MarketingCopy::sheetsMeta();
    $images = \App\Support\MarketingCopy::sheetImages();
    $guide = \App\Support\MarketingCopy::sheetsGuideUrl();
@endphp

<x-marketing-layout current="sheets">
    <x-slot:meta>
        <x-marketing.meta
            :title="$meta['title']"
            :description="$meta['description']"
            :canonical="route('sheets')"
            :schema="\App\Support\MarketingCopy::sheetsSchema()"
        />
    </x-slot:meta>

    <div class="rounded-2xl bg-vtt-panel/95 p-6 sm:p-8">
        <nav aria-label="Okruszki" class="text-sm text-gray-200">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route('home') }}" class="underline decoration-white/30 underline-offset-4">Strona główna</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('tutorial') }}" class="underline decoration-white/30 underline-offset-4">Jak grać</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page">Karty postaci</li>
            </ol>
        </nav>

        <h1 class="mt-6 text-4xl font-semibold leading-tight text-white sm:text-5xl">Karty postaci i szablony</h1>
        <p class="mt-5 max-w-3xl text-lg leading-relaxed text-gray-200">
            Karta postaci w FreeRoll to szablon: pola, które gracze uzupełniają w notatniku albo w panelu tokenu. Wartości zostają w przeglądarce. Przycisk na karcie potrafi od razu rzucić kośćmi na wspólny stół.
        </p>
    </div>

    <article id="czym-jest" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Gdzie karta żyje przy stole</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Dolny panel ma notatniki. Do notatnika wczytujesz szablon z serwera albo własny plik. Ta sama karta może wisieć przy tokenie postaci, w panelu tokenu. Każda otwarta kopia ma własne pola: zmiana punktów życia u wojownika nie rusza karty maga.
        </p>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Nazwa pola, na przykład <code class="text-blue-200">str_mod</code>, jest też nazwą, którą makro czyta jako <code class="text-blue-200">@str_mod</code>. Karta i rzut w panelu kości mówią tym samym językiem.
        </p>
        <div class="mt-6">
            <x-marketing.shot :shot="$images['sheet']" />
        </div>
    </article>

    <article id="dwie-drogi" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Dwie drogi dla Mistrza Gry</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            W bocznym panelu stołu, po zalogowaniu hasłem MG, jest sekcja „Zarządzaj szablonami”. Przycisk „Stwórz w edytorze” składa kartę z sekcji i wierszy: pole tekstowe, obszar tekstu, checkbox albo pole z przyciskiem rzutu. Przy każdym polu podajesz etykietę i identyfikator. Ten identyfikator później wstawiasz do formuł jako <code class="text-blue-200">@nazwa</code>.
        </p>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Druga droga to plik <code class="text-blue-200">.html</code>. Kładziesz go w <code class="text-blue-200">backend/assets/templates/</code> albo wgrywasz z poziomu „Wgraj plik HTML”. Nazwa pliku staje się nazwą na liście: <code class="text-blue-200">dnd_5e.html</code> widać jako „Dnd 5e”. Gotowe karty z edytora da się poprawiać w edytorze. Plik napisany ręcznie, ze skryptem, otwierasz do podglądu albo duplikujesz i dopiero kopię układasz w edytorze.
        </p>
        <div class="mt-6">
            <x-marketing.shot :shot="$images['editor']" />
        </div>
    </article>

    <article id="pola" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Pola, które gracze wypełniają</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            W pliku HTML każde pole dostaje atrybut <code class="text-blue-200">data-field</code> z unikalną nazwą. Ta nazwa trzyma wartość między sesjami. Wygląd dokładają klasy: <code class="text-blue-200">.plain</code> stapia się z tekstem, <code class="text-blue-200">.box</code> ma ramkę, <code class="text-blue-200">.circle</code> jest bańką na cechę. Szerokość: <code class="text-blue-200">.xs</code>, <code class="text-blue-200">.sm</code>, <code class="text-blue-200">.wide</code>.
        </p>
        <pre class="mt-6 overflow-x-auto rounded-lg bg-black/40 p-4 text-sm leading-relaxed text-gray-100"><code>&lt;input data-field="character_name" type="text" class="plain wide"&gt;
&lt;input data-field="hp" type="text" class="box sm" value="12"&gt;
&lt;input data-field="str" type="text" class="circle"&gt;</code></pre>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Zawartość znacznika <code class="text-blue-200">&lt;title&gt;</code> staje się tytułem notatki w chwili wczytania szablonu. Długi opis, ekwipunek i historia postaci lepiej siedzą w <code class="text-blue-200">&lt;textarea&gt;</code>.
        </p>
    </article>

    <article id="rzuty" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Rzut prosto z karty</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Przycisk z <code class="text-blue-200">data-roll</code> rzuca kośćmi i wrzuca wynik do wspólnej historii. Formuła może być stała, <code class="text-blue-200">d20+5</code>, albo brać liczbę z pola, <code class="text-blue-200">d20+@str_mod</code>. Biegłość dopisujesz warunkiem: wartość dochodzi tylko wtedy, gdy zaznaczony jest checkbox.
        </p>
        <pre class="mt-6 overflow-x-auto rounded-lg bg-black/40 p-4 text-sm leading-relaxed text-gray-100"><code>&lt;input data-field="athletics" type="text" class="box xs" value="+3"&gt;
&lt;input data-field="athletics_prof" type="checkbox"&gt;
&lt;button
  data-roll="d20+@athletics+@prof_bonus?@athletics_prof"
  data-roll-label="Atletyka"
  class="roll-btn"&gt;🎲&lt;/button&gt;</code></pre>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            <code class="text-blue-200">data-roll-label</code> to nazwa, którą drużyna widzi w historii, na przykład „Atletyka” albo „Atak: @weapon_name”. W edytorze to samo ustawiasz jako formułę rzutu i etykietę przy wierszu „Pole z przyciskiem rzutu”.
        </p>
    </article>

    <article id="styl-i-skrypt" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Wygląd i liczenie pól</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Do prostej karty wystarczą pola i przyciski. Gdy karta ma własny kolor, w pliku HTML dodajesz blok <code class="text-blue-200">&lt;style&gt;</code>. Style zostają przy tej karcie: druga notatka obok nie dziedziczy tła. Skrypt jest opcjonalny. Dostaje obiekt <code class="text-blue-200">vtt</code> i nim czyta oraz zapisuje pola, na przykład modyfikator cechy ze współczynnika.
        </p>
        <pre class="mt-6 overflow-x-auto rounded-lg bg-black/40 p-4 text-sm leading-relaxed text-gray-100"><code>&lt;script&gt;
  function abilityModifier(score) {
    var n = parseInt(score, 10);
    if (isNaN(n)) return '';
    var m = Math.floor((n - 10) / 2);
    return (m &gt;= 0 ? '+' : '') + m;
  }

  vtt.onFieldChange(function (name) {
    if (name === 'str_score') {
      vtt.setField('str_mod', abilityModifier(vtt.getField('str_score')));
    }
  });
&lt;/script&gt;</code></pre>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            <code class="text-blue-200">vtt.getField</code> czyta pole, <code class="text-blue-200">vtt.setField</code> je zapisuje, <code class="text-blue-200">vtt.onFieldChange</code> reaguje na wpisywanie. Skrypt nie woła się ponownie od własnego <code class="text-blue-200">setField</code>, więc nie wpada w pętlę. Zewnętrznych plików <code class="text-blue-200">&lt;script src&gt;</code> stół nie uruchamia. Pliki z katalogu szablonów są treścią MG: wgrywaj tylko karty, którym ufasz.
        </p>
    </article>

    <article id="zapis" class="mt-16 scroll-mt-8 rounded-xl border border-white/10 bg-vtt-panel/95 p-5 sm:p-8">
        <h2 class="text-3xl font-semibold text-white">Zapis karty</h2>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Uzupełniona karta zostaje w przeglądarce gracza, osobno dla każdego notatnika. Przycisk zapisu oddaje dwa pliki: JSON z szablonem i wartościami pól, albo HTML z wpisanymi liczbami, wygodny do druku. Przyciski rzutu w eksporcie HTML są ukryte. Wczytanie JSON przywraca pola. Wczytanie samego HTML zaczyna od wartości wpisanych w atrybucie <code class="text-blue-200">value</code>.
        </p>
        <p class="mt-4 max-w-3xl text-lg leading-relaxed text-gray-200">
            Pełna składnia pól, scopowania CSS i API <code class="text-blue-200">vtt</code> jest w instrukcji w repozytorium.
            <a href="{{ $guide }}" class="font-semibold text-white underline decoration-white/40 underline-offset-4" target="_blank" rel="noopener noreferrer">Przewodnik tworzenia szablonów na GitHubie</a>.
        </p>
    </article>

    <section class="mt-20 rounded-xl border border-white/10 bg-vtt-panel/95 p-6 sm:p-10" aria-labelledby="po-kartach">
        <h2 id="po-kartach" class="text-3xl font-semibold text-white">Wróć do stołu</h2>
        <p class="mt-3 max-w-3xl text-lg text-gray-200">
            Szablon ląduje na liście w notatniku. Gracz wczytuje go raz i dalej uzupełnia kartę w trakcie sesji.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('tutorial') }}#panel" class="inline-flex min-h-11 items-center rounded-md bg-blue-700 px-5 font-semibold text-white hover:bg-blue-600">
                Notatki przy stole
            </a>
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center rounded-md px-5 font-semibold text-white underline decoration-white/40 underline-offset-4">
                Strona główna
            </a>
        </div>
    </section>
</x-marketing-layout>
