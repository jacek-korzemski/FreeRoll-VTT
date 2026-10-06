<?php

namespace App\Support;

class MarketingCopy
{
    public const GITHUB = 'https://github.com/jacek-korzemski/FreeRoll-VTT';

    public static function homeMeta(): array
    {
        return [
            'title' => 'Darmowy stół VTT do gier RPG online | FreeRoll',
            'description' => 'Darmowy stół VTT do gier RPG online: mapa, tokeny, mgła wojny, kości, notatki i PDF. Graj w przeglądarce, bez instalacji u graczy. Załóż stół w minutę.',
        ];
    }

    public static function tutorialMeta(): array
    {
        return [
            'title' => 'Jak zagrać na darmowym stole VTT | FreeRoll',
            'description' => 'Tutorial FreeRoll: jak założyć darmowy stół VTT, wejść hasłem gracza lub MG i użyć mapy, tokenów, mgły wojny, kości oraz pinga. Wystarczy przeglądarka.',
        ];
    }

    public static function sheetsMeta(): array
    {
        return [
            'title' => 'Karty postaci i szablony VTT | FreeRoll',
            'description' => 'Karty postaci w darmowym stole VTT: pola, rzuty z karty i szablony HTML. MG składa je w edytorze albo wgrywa plik. Dane zostają w przeglądarce przy sesji RPG.',
        ];
    }

    public static function ogImage(): string
    {
        return asset('marketing/og.jpg');
    }

    /**
     * @return array{src: string, alt: string, width: int, height: int}
     */
    public static function image(string $file, string $alt): array
    {
        $path = public_path('marketing/'.$file);
        $size = is_file($path) ? @getimagesize($path) : false;

        return [
            'src' => asset('marketing/'.$file),
            'alt' => $alt,
            'width' => (int) ($size[0] ?? 1600),
            'height' => (int) ($size[1] ?? 900),
        ];
    }

    public static function heroImage(): array
    {
        return self::image(
            'mapa.webp',
            'Plansza FreeRoll: siatka, tło mapy i tokeny postaci podczas sesji RPG w przeglądarce.',
        );
    }

    /**
     * @return list<array{title: string, text: string, href: string}>
     */
    public static function startSteps(): array
    {
        return [
            [
                'title' => 'Załóż konto',
                'text' => 'Wystarczy imię, nazwa użytkownika, e-mail i hasło. Nazwa użytkownika wchodzi w adres Twoich stołów i później się nie zmienia.',
                'href' => route('register'),
            ],
            [
                'title' => 'Utwórz stół',
                'text' => 'Podajesz nazwę, hasło gracza, osobne hasło Mistrza Gry, język interfejsu i motyw kolorystyczny. Na koncie są trzy stoły, a na każdy 50 MB map, tokenów, teł i PDF-ów.',
                'href' => route('tutorial').'#stol',
            ],
            [
                'title' => 'Zaproś drużynę',
                'text' => 'Wysyłasz link do stołu. Gracze wchodzą hasłem gracza, Ty hasłem MG. Od tej chwili wszyscy widzą tę samą scenę, bez instalowania programu.',
                'href' => route('tutorial').'#logowanie',
            ],
        ];
    }

    /**
     * @return list<array{title: string, text: string, href: string}>
     */
    public static function features(): array
    {
        $tutorial = route('tutorial');

        return [
            [
                'title' => 'Sceny',
                'text' => 'Karczma, loch i pole bitwy to osobne sceny. Przełączenie widzą wszyscy, a każda scena trzyma własne tło, mgłę i tokeny.',
                'href' => $tutorial.'#sceny',
            ],
            [
                'title' => 'Tło i siatka',
                'text' => 'Plansza opiera się na polu 64×64 pikseli. Tło przesuwasz, skalujesz i resetujesz, a siatkę możesz schować, gdy rysunek ma zostać czystą ilustracją.',
                'href' => $tutorial.'#mapa',
            ],
            [
                'title' => 'Tokeny i elementy mapy',
                'text' => 'Postacie i przedmioty stawiasz na polach. Stół nie pozwala położyć dwóch rzeczy na jednym polu, a gumka szybko sprząta planszę.',
                'href' => $tutorial.'#tokeny',
            ],
            [
                'title' => 'Mgła wojny',
                'text' => 'Mistrz Gry odkrywa i zakrywa pola pędzlem. Gracze widzą tylko to, co im pokażesz. Podgląd 50% zostawia mapę widoczną dla Ciebie.',
                'href' => $tutorial.'#mgla',
            ],
            [
                'title' => 'Kości',
                'text' => 'd4, d6, d8, d10, d12, d20 i d100, modyfikator i wspólna historia rzutów.',
                'href' => $tutorial.'#kosci',
            ],
            [
                'title' => 'Notatki i szablony',
                'text' => 'Do trzech notatników naraz. Zwykły tekst albo szablon HTML, na przykład karta postaci, z zapisem do JSON i eksportem do HTML.',
                'href' => $tutorial.'#panel',
            ],
            [
                'title' => 'Makra',
                'text' => 'Rzut zapisujesz raz, na przykład 2d6+@str. Makro czyta pola z karty w notatniku i wrzuca wynik do historii kości.',
                'href' => $tutorial.'#panel',
            ],
            [
                'title' => 'Czytnik PDF',
                'text' => 'Podręczniki z serwera otwierasz przy mapie. Lokalny PDF zostaje tylko w Twojej przeglądarce i nie zajmuje limitu stołu.',
                'href' => $tutorial.'#panel',
            ],
            [
                'title' => 'Ping',
                'text' => 'Mistrz Gry klika pole, a widok drużyny przewija się tam z podświetleniem. Ping da się zdjąć, żeby nie ciągnął kolejnych graczy.',
                'href' => $tutorial.'#ping',
            ],
            [
                'title' => 'Liczniki',
                'text' => 'Ręczne albo czasowe. Mogą zostać u Ciebie albo pojawić się u wszystkich przy stole, na przykład jako runda albo pochodnia.',
                'href' => $tutorial.'#panel',
            ],
        ];
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function faqs(): array
    {
        return [
            [
                'q' => 'Czy ten stół VTT jest naprawdę darmowy?',
                'a' => 'Tak. Konto i gra nic nie kosztują. Kod FreeRoll jest otwarty i leży na GitHubie.',
            ],
            [
                'q' => 'Czy gracze muszą coś instalować?',
                'a' => 'Nie. Wystarczy przeglądarka, link do stołu i hasło. Nie ma aplikacji na telefon ani programu na komputer.',
            ],
            [
                'q' => 'Czym różni się hasło gracza od hasła Mistrza Gry?',
                'a' => 'Hasło gracza otwiera stół w trybie uczestnika: mapa, kości, notatki, PDF. Hasło MG dodaje mgłę wojny, sceny, wgrywanie plików i ping. Oba hasła ustawiasz przy tworzeniu stołu i możesz je później zmienić w panelu.',
            ],
            [
                'q' => 'Ile stołów mogę założyć?',
                'a' => 'Domyślnie trzy na konto. Każdy ma własny adres, własne hasła i 50 MB na wgrane materiały: tokeny, elementy mapy, tła, szablony HTML i PDF.',
            ],
            [
                'q' => 'Czy mogę postawić FreeRoll u siebie?',
                'a' => 'Tak. Jedna paczka PHP to jeden pokój, a Table Manager obsługuje wiele stołów na jednym hostingu. Ta strona informacyjna włącza się tylko przy SEO=true. Własna instalacja domyślnie pokazuje sam ekran logowania.',
            ],
            [
                'q' => 'Czy mogę użyć zwykłego hasła i głównego e-maila?',
                'a' => 'Lepiej nie. FreeRoll to projekt hobbystyczny, prowadzony po godzinach. Załóż konto na adresie, którego nie używasz do banku i poczty, i wymyśl hasło tylko do tego stołu.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, paragraphs: list<string>, image: array{src: string, alt: string, width: int, height: int}}>
     */
    public static function steps(): array
    {
        return [
            [
                'id' => 'stol',
                'title' => 'Załóż stół w panelu',
                'paragraphs' => [
                    'Po zalogowaniu widzisz swoje stoły i formularz „Nowy stół”. Wpisujesz nazwę, hasło gracza, hasło Mistrza Gry, język interfejsu (polski albo angielski) i szablon kolorystyczny.',
                    'Każdy stół dostaje własny adres, w postaci /vtt/user/twoja-nazwa/…. Na koncie mieszczą się trzy takie pokoje. Limit wgranych plików to 50 MB na stół. Hasła zmienisz później przy stole, bez ruszania konta.',
                ],
                'image' => self::image(
                    'panel-stol.webp',
                    'Panel FreeRoll z formularzem nowego stołu: nazwa, hasło gracza, hasło Mistrza Gry, język i szablon kolorystyczny.',
                ),
            ],
            [
                'id' => 'logowanie',
                'title' => 'Wejdź hasłem gracza albo MG',
                'paragraphs' => [
                    'Stół ma osobną stronę logowania, niezależną od konta w panelu. Gracze wpisują hasło gracza. Ty, gdy prowadzisz, wpisujesz hasło MG.',
                    'Drużynie wysyłasz link i hasło gracza. Hasła MG nie podawaj przy stole, jeśli odkrywanie mapy i zmiana scen mają zostać u Ciebie.',
                ],
                'image' => self::image(
                    'logowanie-stol.webp',
                    'Ekran logowania stołu FreeRoll z polem hasła i przyciskiem wejścia do gry.',
                ),
            ],
            [
                'id' => 'mapa',
                'title' => 'Mapa, siatka i tło',
                'paragraphs' => [
                    'Plansza to siatka o polu 64×64 pikseli. Tło, czyli rysunek lochu, lasu albo wnętrza, MG wybiera z biblioteki albo wgrywa w sekcji „Dodaj materiały”.',
                    'Tło da się przesunąć, powiększyć i zresetować. Siatkę można ukryć, kiedy ilustracja ma być czysta. Lupa zmienia powiększenie Twojego widoku.',
                ],
                'image' => self::image(
                    'mapa.webp',
                    'Mapa sesji RPG na siatce FreeRoll, z tłem i widocznymi polami planszy.',
                ),
            ],
            [
                'id' => 'tokeny',
                'title' => 'Tokeny i elementy mapy',
                'paragraphs' => [
                    'W bocznym panelu są dwie listy: elementy mapy (drzwi, meble, znaczniki) i tokeny postaci. Wybierasz zasób i stawiasz go na polu.',
                    'Jedno pole przyjmuje jedną rzecz, więc tokeny nie wchodzą sobie na głowy. Gumka zdejmuje obiekty, tryb przesuwania poprawia ustawienie, a prawy przycisk myszy usuwa to, co stoi na polu.',
                ],
                'image' => self::image(
                    'tokeny.webp',
                    'Tokeny postaci ustawione na polach siatki, obok listy tokenów w bocznym panelu.',
                ),
            ],
            [
                'id' => 'mgla',
                'title' => 'Mgła wojny',
                'paragraphs' => [
                    'Mgła jest narzędziem MG. Włączasz ją, wchodzisz w edycję i pędzlem odkrywasz albo zakrywasz pola. Rozmiar pędzla zmieniasz suwakiem.',
                    'Są też akcje „Odkryj wszystko” i „Zakryj wszystko”. Podgląd z przezroczystością 50% pokazuje Tobie mapę pod mgłą, podczas gdy gracze widzą tylko odkryte pola.',
                ],
                'image' => self::image(
                    'mgla.webp',
                    'Mgła wojny na mapie: część pól zakryta, a w panelu MG włączona edycja pędzla.',
                ),
            ],
            [
                'id' => 'sceny',
                'title' => 'Sceny',
                'paragraphs' => [
                    'Jedna sesja to często kilka miejsc. Scena ma własne tło, mgłę, tokeny i elementy mapy. MG dodaje scenę, zmienia jej nazwę, duplikuje albo usuwa.',
                    'Przełączenie sceny dzieje się u wszystkich od razu. Gracze nie wybierają mapy sami: widzą tę, którą właśnie otworzył MG.',
                ],
                'image' => self::image(
                    'sceny.webp',
                    'Lista scen w panelu Mistrza Gry, z możliwością dodania, zmiany nazwy i przełączenia mapy.',
                ),
            ],
            [
                'id' => 'kosci',
                'title' => 'Kości w przeglądarce',
                'paragraphs' => [
                    'Panel „Rzut kośćmi” ma d4, d6, d8, d10, d12, d20 i d100. Składasz pulę, dopisujesz modyfikator i rzucasz. Imię gracza zostaje zapisane w przeglądarce.',
                    'Wynik wpada do wspólnej historii, więc widzą go pozostali przy stole.',
                ],
                'image' => self::image(
                    'kosci.webp',
                    'Panel rzutu kośćmi FreeRoll z wyborem kości, modyfikatorem i historią wyników.',
                ),
            ],
            [
                'id' => 'panel',
                'title' => 'Notatki, makra, PDF i liczniki',
                'paragraphs' => [
                    'Dolny panel trzyma narzędzia sesji. Notatniki, do trzech naraz, przyjmują tekst albo szablon HTML, na przykład kartę postaci. Zapiszesz je jako JSON albo wyeksportujesz do HTML.',
                    'Makra rzucają z wyrażeń takich jak 2d6+@str i czytają nazwane pola z szablonu w notatniku. Czytnik PDF otwiera pliki z serwera oraz lokalne PDF-y, które zostają tylko u Ciebie. Liczniki, ręczne albo czasowe, MG może pokazać całej drużynie.',
                ],
                'image' => self::image(
                    'panel-dolny.webp',
                    'Dolny panel stołu z otwartymi notatkami i zakładkami czytnika PDF, makr oraz liczników.',
                ),
            ],
            [
                'id' => 'ping',
                'title' => 'Ping, czyli przywołanie uwagi',
                'paragraphs' => [
                    'Narzędzie „Przyciągnij uwagę” jest dla MG. Klikasz pole na mapie, a widok pozostałych przewija się w to miejsce i podświetla je.',
                    'Ping da się usunąć. Dzięki temu ktoś, kto dołączy później, nie zostanie ściągnięty do starego znacznika.',
                ],
                'image' => self::image(
                    'ping.webp',
                    'Podświetlony ping na polu mapy, którym Mistrz Gry przywołuje widok graczy.',
                ),
            ],
        ];
    }

    public static function homeSchema(): array
    {
        $meta = self::homeMeta();
        $home = route('home');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#website',
                    'name' => 'FreeRoll VTT',
                    'url' => $home,
                    'inLanguage' => 'pl-PL',
                    'description' => $meta['description'],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'FreeRoll VTT',
                    'applicationCategory' => 'GameApplication',
                    'operatingSystem' => 'Web',
                    'url' => $home,
                    'description' => $meta['description'],
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'PLN',
                    ],
                    'isAccessibleForFree' => true,
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn (array $faq): array => [
                        '@type' => 'Question',
                        'name' => $faq['q'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $faq['a'],
                        ],
                    ], self::faqs()),
                ],
            ],
        ];
    }

    public static function tutorialSchema(): array
    {
        $meta = self::tutorialMeta();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => 'Jak zagrać na darmowym stole VTT FreeRoll',
            'description' => $meta['description'],
            'inLanguage' => 'pl-PL',
            'totalTime' => 'PT10M',
            'step' => array_map(function (array $step, int $index): array {
                return [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'name' => $step['title'],
                    'text' => implode(' ', $step['paragraphs']),
                    'url' => route('tutorial').'#'.$step['id'],
                    'image' => $step['image']['src'],
                ];
            }, self::steps(), array_keys(self::steps())),
        ];
    }

    public static function sheetsGuideUrl(): string
    {
        return self::GITHUB.'/blob/main/TWORZENIE-SZABLONÓW.md';
    }

    /**
     * @return array{src: string, alt: string, width: int, height: int}
     */
    public static function sheetImages(): array
    {
        return [
            'editor' => self::image(
                'edytor-szablonu.webp',
                'Edytor szablonów Mistrza Gry: sekcje, wiersze pól i przycisk zapisu karty postaci.',
            ),
            'sheet' => self::image(
                'karta-notatnik.webp',
                'Karta postaci w notatniku stołu, z polami do uzupełnienia i przyciskiem rzutu kością.',
            ),
        ];
    }

    public static function sheetsSchema(): array
    {
        $meta = self::sheetsMeta();
        $url = route('sheets');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => 'Karty postaci i szablony w FreeRoll VTT',
            'description' => $meta['description'],
            'inLanguage' => 'pl-PL',
            'url' => $url,
            'image' => self::sheetImages()['sheet']['src'],
        ];
    }
}
