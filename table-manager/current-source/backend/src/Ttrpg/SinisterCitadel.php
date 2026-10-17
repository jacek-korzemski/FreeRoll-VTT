<?php

namespace FreeRoll\Ttrpg;

/**
 * GM notes for one night in Crylla's auction house.
 * Wording is original. Floor plans and player props are image references.
 */
final class SinisterCitadel
{
    private const DIR = 'backend/assets/map/sinister-citadel/';

    /**
     * @return array<string, mixed>
     */
    public static function package(): array
    {
        return [
            'format' => AdventurePackage::FORMAT,
            'version' => AdventurePackage::VERSION,
            'entries' => self::entries(),
            'tabs' => [
                'tabKeys' => ['overview'],
                'activeKey' => 'overview',
            ],
        ];
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, tabs: array{tabIds: int[], activeId: int|null}}
     */
    public static function install(): array
    {
        return AdventurePackage::apply(AdventureStore::make(), self::package());
    }

    /**
     * @return array<int, array{key: string, title: string, sortOrder: int, bodyHtml: string}>
     */
    private static function entries(): array
    {
        $rows = [];
        $add = static function (string $key, string $title, string $html) use (&$rows): void {
            $rows[] = [
                'key' => $key,
                'title' => $title,
                'sortOrder' => count($rows),
                'bodyHtml' => $html,
            ];
        };

        $add('overview', 'Przygoda', self::overview());
        $add('broadgard', 'Broadgard', self::broadgard());
        $add('citadel', 'Cytadela Crylli', self::citadel());
        $add('hooks', 'Haki', self::hooks());
        $add('timeline', 'Oś czasu', self::timeline());
        $add('beginning', 'Początek', self::beginning());
        $add('event-1', 'Zdarzenie 1: przybycie', self::event1());
        $add('shirf', 'Shirf Crylla', self::shirf());
        $add('jisper', 'Jisper Klugfist', self::jisper());
        $add('figg', 'Figg Plyth', self::figg());
        $add('area-1', 'Area 1: sala aukcyjna', self::area1());
        $add('area-1-1', 'Area 1-1: hol', self::area11());
        $add('area-1-2', 'Area 1-2: galeria', self::area12());
        $add('area-1-3', 'Area 1-3: biuro Shirfa', self::area13());
        $add('area-1-4', 'Area 1-4: straż i obsługa', self::area14());
        $add('merchants-friend', 'Przyjaciel kupca', self::merchantsFriend());
        $add('event-2', 'Zdarzenie 2: Figg przybywa', self::event2());
        $add('vile-ooze', 'Ohydny szlam', self::vileOoze());
        $add('area-2', 'Area 2: piwnica NW', self::area2());
        $add('area-2-1', 'Area 2-1: przedsionek skarbca', self::area21());
        $add('area-2-2', 'Area 2-2: skarbiec', self::area22());
        $add('area-3', 'Area 3: piwnica NE', self::area3());
        $add('area-3-1', 'Area 3-1: wspólna sala', self::area31());
        $add('area-3-2', 'Area 3-2: kwatera kapitana', self::area32());
        $add('area-3-3', 'Area 3-3: kwatera straży', self::area33());
        $add('area-3-4', 'Area 3-4: spiżarnia', self::area34());
        $add('area-3-5', 'Area 3-5: zawalone kwatery', self::area35());
        $add('devoted-sentinel', 'Oddany strażnik', self::devoted());
        $add('event-3', 'Zdarzenie 3: włamanie', self::event3());
        $add('apprentice', 'Uczeń włamywacza', self::apprentice());
        $add('general', 'Generał', self::general());
        $add('concluding', 'Zakończenie', self::concluding());
        $add('epilogue', 'Epilog: pościg za Jisperem', self::epilogue());
        $add('area-4', 'Area 4: The Blinded Troll', self::area4());
        $add('taben', 'Taben Vul', self::taben());
        $add('items', 'Przedmioty aukcji', self::items());
        $add('censer', 'Kadzielnica Gurga', self::censer());
        $add('oracle', 'Wyrocznia wieśniaka', self::oracle());
        $add('handkerchief', 'Chustka ukrycia', self::handkerchief());
        $add('anchor', 'Kotwica Breedle’a', self::anchor());
        $add('scroll', 'Zwój przeniesienia', self::scroll());
        $add('decanter', 'Karafka szlamów', self::decanter());
        $add('iron-passage', 'Czar: żelazne przejście', self::ironPassage());
        $add('handout-1', 'Rekwizyt: list Rimina', self::handout1());
        $add('handout-2', 'Rekwizyt: notatki Shirfa', self::handout2());
        $add('handout-3', 'Rekwizyt: pieczęć Crylli', self::handout3());
        $add('handout-4', 'Rekwizyt: tekst aukcji', self::handout4());

        return $rows;
    }

    private static function img(string $file, string $alt): string
    {
        $src = self::DIR . $file;
        $alt = htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<p><img class="adv-ref" src="' . $src . '" data-src="' . $src . '" data-asset-type="map" alt="' . $alt . '"></p>';
    }

    private static function overview(): string
    {
        return '<p>Jedna noc w domu aukcyjnym rodu Crylla, w starej cytadeli na wschodzie <a href="#" class="adv-entry-link" data-entry-id="broadgard">Broadgardu</a>. Cztery do sześciu postaci na 2. poziomie. Da się zamknąć w jednej sesji.</p>'
            . '<p>Drużyna zostaje nocną strażą przed pierwszą aukcją <a href="#" class="adv-entry-link" data-entry-id="shirf">Shirf Crylli</a>. Nad ranem ma już nie być na miejscu, kiedy wraca rodzina. Prawdziwy problem nie jest w tłumie gości, tylko pod podłogą i w tym, kogo Shirf wynajęła.</p>'
            . '<h2>Jak to puścić</h2>'
            . '<ul>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="hooks">Haki</a> — trzy sposoby, żeby w ogóle dostali tę robotę.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="timeline">Oś czasu</a> i <a href="#" class="adv-entry-link" data-entry-id="beginning">początek</a> na ulicy przy dokach.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-1">Zdarzenie 1</a> — oprowadzanie. Potem krótka swoboda.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-2">Zdarzenie 2</a> — <a href="#" class="adv-entry-link" data-entry-id="figg">Figg Plyth</a> dowozi resztę towaru, w tym <a href="#" class="adv-entry-link" data-entry-id="general">Generała</a>.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-3">Zdarzenie 3</a> — włamanie ze skarbca. Stąd robi się bałagan.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="concluding">Zakończenie</a> o świcie. Jeśli zostaną, <a href="#" class="adv-entry-link" data-entry-id="epilogue">epilog</a> w <a href="#" class="adv-entry-link" data-entry-id="area-4">The Blinded Troll</a>.</li>'
            . '</ul>'
            . '<h2>Miejsca</h2>'
            . '<ul>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="citadel">Cytadela</a> — cechy całego budynku.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="area-1">Area 1</a> parter, <a href="#" class="adv-entry-link" data-entry-id="area-2">Area 2</a> piwnica skarbca, <a href="#" class="adv-entry-link" data-entry-id="area-3">Area 3</a> zawalona piwnica, <a href="#" class="adv-entry-link" data-entry-id="area-4">Area 4</a> karczma w Tiraghin.</li>'
            . '</ul>'
            . '<h2>Tylko dla MG</h2>'
            . '<p>Gildia złodziei ma plan budynku od robotników, ale nie wie o pułapkach na skarbcu i uważa towar za tani. Dlatego przysyła <a href="#" class="adv-entry-link" data-entry-id="apprentice">uczniów</a>, nie mistrzów. Kopią od tygodnia i wyjdą przez żelazną podłogę <a href="#" class="adv-entry-link" data-entry-id="iron-passage">żelaznym przejściem</a>.</p>'
            . '<p><a href="#" class="adv-entry-link" data-entry-id="jisper">Jisper</a> jest oszustem. Matka Shirfa płaci mu grubo, on zatrudnia tanich strażników i resztę zatrzymuje. Tej nocy nie patroluje obwodu. Pije kilka ulic dalej i wraca dopiero o świcie.</p>'
            . '<p>Figg w tajemnicy dowozi naprawdę cenne rzeczy, akurat kiedy złodzieje kończą tunel. Otwarcie drzwi skarbca od środka odpala wardę. Budzi się Generał, ożywają <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">zbroje w holu</a>, a <a href="#" class="adv-entry-link" data-entry-id="devoted-sentinel">duchy straży</a> wychodzą z zawalonej kwatery.</p>'
            . '<p>Lista towaru: <a href="#" class="adv-entry-link" data-entry-id="items">przedmioty</a>. Kartki dla graczy: <a href="#" class="adv-entry-link" data-entry-id="handout-1">list</a>, <a href="#" class="adv-entry-link" data-entry-id="handout-2">notatki</a>, <a href="#" class="adv-entry-link" data-entry-id="handout-3">pieczęć</a>, <a href="#" class="adv-entry-link" data-entry-id="handout-4">tekst aukcji</a>.</p>';
    }

    private static function broadgard(): string
    {
        return '<p>Miasto, które wyrosło z czterech sąsiednich farm. Doki na rzece Red Ash, blisko lasu Verda i kopalń Chiselfist. Mury są wysokie, gildie i szkoły mają się dobrze, a przyjezdni znajdą i łóżko, i robotę.</p>'
            . '<p>Wschodnia dzielnica przy dokach jest gorsza. Straż miejska podobno przymyka oko, jeśli ktoś zapłaci. Tam stoi <a href="#" class="adv-entry-link" data-entry-id="citadel">stara cytadela</a>, od niedawna dom aukcyjny rodu Crylla. Jedna z siedmiu cytadel miasta. Tę wyłączono prawie sto lat temu, kiedy domknięto mury. Pozostałe sześć nadal służy.</p>'
            . '<p>Ród Crylla to tieflingowie, kupcy z opinią dziwaków, ale uczciwych. Matka rodu, Maif Crylla, jest dumna z córki i ostrożna. To ona płaci <a href="#" class="adv-entry-link" data-entry-id="jisper">Jisperowi</a> za ochronę pierwszej aukcji. <a href="#" class="adv-entry-link" data-entry-id="figg">Figg Plyth</a>, stary przyjaciel rodu, spłaca Maif dług, ściągając bogatych znajomych na tę właśnie noc.</p>'
            . '<p>Karczma Broken Oak stoi naprzeciwko cytadeli. Stamtąd Jisper miał niby patrzeć na drzwi. Nie patrzy. Wioska Tiraghin, pół dnia konno, jest w <a href="#" class="adv-entry-link" data-entry-id="epilogue">epilogu</a>.</p>';
    }

    private static function citadel(): string
    {
        return self::img('area-1-auction-hall.jpg', 'Plan parteru domu aukcyjnego Crylli')
            . '<p>Gruby kamień, bez okien. Na parterze sufity mają 30 stóp, w piwnicach 15. Dach jest łupkowy. Reszta skrzydeł albo się sypie, albo jest w remoncie i tej nocy nie wchodzi do gry. Gra się w sali aukcyjnej i dwóch piwnicach.</p>'
            . '<p><a href="#" class="adv-entry-link" data-entry-id="area-1">Area 1</a> jest jasna: ciągłe płomienie udają świece i latarnie. <a href="#" class="adv-entry-link" data-entry-id="area-2">Piwnica północno-zachodnia</a> jest tylko przyćmiona, żeby goście czuli, że to nie dla nich. <a href="#" class="adv-entry-link" data-entry-id="area-3">Piwnica północno-wschodnia</a> jest ciemna.</p>'
            . '<p>Ulica nie słychać przez drzwi. Po oprowadzeniu nad rzeką przetacza się grzmot. Kiedy drzwi się zamykają na noc, zaczyna się ulewa i błyskawice. Deszcz słychać przez dach.</p>'
            . '<p>Front to nowe, okute drewniane drzwi. Nad nimi zielony szyld z żółtym napisem Crylla Auction House, przywiązany między dwoma żerdziami. Kamień wokół kruszeje. Przy dokach tej nocy nie widać straży miejskiej.</p>'
            . '<p>Kolory rodu: ciemna zieleń i żółć. Herb jest na posadzce holu i na mównicy. Ten na podłodze jest częścią wardy skarbca. Pieczęć, którą Shirf każe pokazać Jisperowi, jest w <a href="#" class="adv-entry-link" data-entry-id="handout-3">rekwizycie</a>.</p>';
    }

    private static function hooks(): string
    {
        return '<p>Zaczynają już w murach <a href="#" class="adv-entry-link" data-entry-id="broadgard">Broadgardu</a>, szukając noclegu albo roboty. Wybierz jeden hak albo złóż własny. Każdy kończy się tym samym: nocna straż w <a href="#" class="adv-entry-link" data-entry-id="citadel">domu aukcyjnym</a>, spotkanie z <a href="#" class="adv-entry-link" data-entry-id="jisper">Jisperem</a> i <a href="#" class="adv-entry-link" data-entry-id="shirf">Shirf</a> wieczór przed aukcją.</p>'
            . '<h2>Martwa eskorta</h2>'
            . '<p>Dzień drogi przed miastem leżą szczątki straży, którą wynajął ród Crylla. Przy nich jest list od Jispera: obowiązki i zapłata po aukcji. Mieli się stawić jutro wieczorem przy dokach. Z listu wynika, że Jisper nie zna ich z twarzy. Wie tylko, że mieli być godni zaufania. Drużyna może przyjść w ich miejsce.</p>'
            . '<h2>Rodzinny klejnot</h2>'
            . '<p>Ktoś z drużyny dostaje wieść, że zaginiony rodzinny przedmiot wrócił i idzie na aukcję. Listy polecające już poszły, a ich wynajęto do pilnowania domu noc wcześniej. Da się podmienić klejnot na podróbkę, jeśli nikt nie patrzy. Prawdziwy towar siedzi w <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbcu</a>.</p>'
            . '<h2>Broken Oak</h2>'
            . '<p>Przy piwie podchodzi człowiek, który przedstawia się jako Jisper. Mówi, że wpadła mu robota: jedna noc przy skarbcu, który właśnie zapieczętowano. Obiecuje 1% zysku aukcji do podziału między strażników. Drugi procent, o którym nie mówi, Shirf i tak przeznacza dla niego jako dla pośrednika. Prawdziwa oferta Shirfa to 2%.</p>';
    }

    private static function timeline(): string
    {
        return '<ol>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-1">Przybycie</a>. Krótka wycieczka po parterze i do kraty skarbca. Shirf wychodzi. Jisper mówi, że idzie patrolować z zewnątrz. Nie idzie.</li>'
            . '<li>Swoboda. Zdążą zajrzeć do <a href="#" class="adv-entry-link" data-entry-id="area-1-3">biura</a> i <a href="#" class="adv-entry-link" data-entry-id="area-2-1">przedsionka skarbca</a>. Jeśli omijają <a href="#" class="adv-entry-link" data-entry-id="area-3">północno-wschodnią piwnicę</a>, i tak dochodzi stamtąd głuchy dźwięk, a potem lekki wstrząs.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-2">Figg</a> przywozi dodatkowy towar i zaraz znika. Nie ma czasu go spokojnie obejrzeć.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="event-3">Włamanie</a>. Złodzieje wchodzą do skarbca zwojem, bez dziury w podłodze. Jedyna droga na zewnątrz to drzwi od środka. To odpala pułapki. Uciekają frontem. Zaczyna się zamęt.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="concluding">Świt</a>. Jisper wraca. Drużyna albo ucieka z nim, albo zostaje i mówi Shirf, co się stało.</li>'
            . '<li>Jeśli zostali i obronili towar, Figg najmuje ich do pościgu. <a href="#" class="adv-entry-link" data-entry-id="epilogue">Epilog</a>.</li>'
            . '</ol>';
    }

    private static function beginning(): string
    {
        return '<p>Są już wynajęci. Mają przyjść wypoczęci, na krótkie oprowadzenie i rozkaz na noc. Wieczór, wschodni Broadgard, zapach rzeki, tłok doków. Kieszonkowcy mieszają się ze sprzedawcami jedzenia. Przy starej cytadeli nie ma ani jednego strażnika.</p>'
            . '<p>Mury się sypią. Drzwi są nowe. Szyld między żerdziami mówi, że to Crylla Auction House. Szczegóły budynku: <a href="#" class="adv-entry-link" data-entry-id="citadel">cytadela</a>. Pukają i zaczyna się <a href="#" class="adv-entry-link" data-entry-id="event-1">zdarzenie 1</a>.</p>';
    }

    private static function event1(): string
    {
        return '<p>Jedno skrzydło drzwi otwiera się do środka. W holu, przy żyrandolu ze świec, które nie dymią, stoją dwie osoby. Niska kobieta w zielonej szacie uśmiecha się. Mężczyzna po jej lewej patrzy jedynym okiem, drugie zakrywa opaska. Wpuszcza ich i zamyka drzwi. To <a href="#" class="adv-entry-link" data-entry-id="shirf">Shirf</a> i <a href="#" class="adv-entry-link" data-entry-id="jisper">Jisper</a>. Hol: <a href="#" class="adv-entry-link" data-entry-id="area-1-1">Area 1-1</a>.</p>'
            . '<p>Kiedy Shirf uzna, że to wynajęta straż, oprowadza po <a href="#" class="adv-entry-link" data-entry-id="area-1">parterze</a> i do kraty <a href="#" class="adv-entry-link" data-entry-id="area-2-1">przedsionka skarbca</a>. Do środka skarbca nie wpuszcza. Pokazuje drzwi i mówi, że jest warda, bez szczegółów. Nie pyta ich o życie. Zakłada, że Jisper już ich sprawdził.</p>'
            . '<p>Jisper idzie z nimi, zadaje pytania i pokazuje, co sprawdzać w nocy. Kto ma bierną Wnikliwość 14, widzi, że na ochronie się nie zna. Dopytany, denerwuje się: mają robić swoje, a szczegóły zostawić jemu. Przepycha grupę najszybciej, jak się da.</p>'
            . '<p>Shirf chętnie odpowiada, dopóki pytań nie robi się za dużo. Wtedy Jisper urywa wycieczkę: przypomina jej o spotkaniu ze sprzedawcą po drugiej stronie miasta.</p>'
            . '<h2>Co da się z niej wyciągnąć</h2>'
            . '<ul>'
            . '<li>Schody na północnym wschodzie są odgrodzone liną. Ściany na dole grożą zawaleniem. Goście i straż mają tam nie schodzić. To <a href="#" class="adv-entry-link" data-entry-id="area-3">Area 3</a>.</li>'
            . '<li>Cytadela była jedną z siedmiu. Wyłączono ją prawie sto lat temu. Sześć pozostałych nadal działa.</li>'
            . '<li>Skarbiec ma wardę i pułapki. Więcej nie mówi.</li>'
            . '<li>Aukcja jutro w południe. Ponad dwudziestu gości, wszyscy znani rodowi. Drużyna schodzi ze służby, kiedy rano wraca Shirf z rodziną.</li>'
            . '<li>Większość towaru już jest w skarbcu i jest zapieczętowana.</li>'
            . '<li>Słyszała, jak matka mówi przyjaciółce, że „wuj Figg” może podrzucić coś na ostatnią chwilę. Shirf nie wierzy, że przyjedzie.</li>'
            . '</ul>'
            . '<h2>Kiedy zostają sami</h2>'
            . '<p>Shirf dziękuje. W biurze jest jedzenie. Każdego poza wujem Figgiem mają grzecznie odesłać do rana. Jisper odprowadza ją za próg, a kiedy drzwi się zamykają, odwraca się do drużyny.</p>'
            . '<p>Mają zaryglować drzwi, kiedy wyjdzie. On niby obchodzi budynek i patrzy z Broken Oak po drugiej stronie ulicy. Ktoś ma stale stać przy skarbcu. Figg Plyth może przyjść z towarem. Po jego wyjściu znowu ryglują drzwi.</p>'
            . '<p>Daje klucz do kraty skarbca (ten sam pasuje do kraty schodów północno-wschodnich), odpowiada na jedno albo dwa pytania i znika. Nie zamierza chodzić wokół muru. Idzie pić kilka przecznic dalej i wróci o świcie, zanim Shirf. Kto wyjdzie do Broken Oak albo obejdzie budynek, Jispera nie znajdzie.</p>'
            . '<p>Zapłata, o której mówi Shirf: 2% utargu. Szacuje utarg na około 12&nbsp;000 złota, czyli około 240 złota dla drużyny. Jisper w haku z karczmy mówił o 1%.</p>';
    }

    private static function shirf(): string
    {
        return '<p>Tiefling, 24 lata, najmłodsza córka Maif Crylli. Słabe powiązania z resztą kupców Broadgardu, ale ród jest szanowany i ona to niesie w postawie. Chce własnego imienia, nie tylko nazwiska. Trochę szkolona na czarodziejkę, magia cienka. Naiwna, nie głupia. Ufa, że wynajęci ludzie zrobią robotę.</p>'
            . '<p>Szata ciemnozielona, szyta grubą złotą nicią. Żółty kaptur podbity zielenią, runy na kapturze. Straż miejska po nich poznaje członka rodu.</p>'
            . '<p>Do walki weź cult fanatic ze zbioru zasad, ale wyrzuć jego czary. Zamiast nich jest czarodziejką 3. poziomu. Charyzma, ST czaru 13, +5 do ataków czarem.</p>'
            . '<ul>'
            . '<li>Sztuczki: mage hand, mending, ray of frost.</li>'
            . '<li>1. poziom, 4 sloty: charm person, detect magic, disguise self.</li>'
            . '<li>2. poziom, 2 sloty: detect thoughts.</li>'
            . '</ul>'
            . '<p>Dwa klucze do skarbca nosi przy sobie. Klucze w szufladzie biurka nie otwierają niczego w cytadeli. Zna słowo, które gasi wardę herbu w holu. Jakie — moduł tego nie podaje. Jeśli drużyna ma je poznać, musi je od niej wyciągnąć albo je wymyślisz.</p>'
            . '<p>Rano, po katastrofie, najpierw milczy i łata krzesła sztuczką mending. Potem się śmieje: goście pokochają tę historię. Szczegóły w <a href="#" class="adv-entry-link" data-entry-id="concluding">zakończeniu</a>.</p>';
    }

    private static function jisper(): string
    {
        return '<p>Człowiek, chaotyczny neutralny. Nazywają go też Chwalipiętą. Oszust. Brał udział w szemranych robotach, ale umie mało. Opowiada zmyślone historie dawnych wyczynów. Leniuszek, który udaje nawróconego łotra i najmuje się kupcom jako doradca od ochrony.</p>'
            . '<p>Skóra jest nowa, bez nacięć. Ciemnobrązowa opaska na lewym oku. Mówi, że stracił je w walce z orkami. Przegrał je w zakładzie z czarownikiem. Równoległe blizny na prawym policzku miały być od wykluwającego się smoczego jaja. Są od butelki w bójce. Dwa wycięcia w prawym uchu to kara za kradzież w Minlop. Tam już go nie wpuszczają. O uchu nie mówi sam.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>15 (ćwiekowana skóra)</td></tr>'
            . '<tr><th>PW</th><td>22 (4d8+4)</td></tr>'
            . '<tr><th>Szybkość</th><td>30 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 12 (+1), Zr 16 (+3), Bd 11 (+1), Int 12 (+1), Mdr 9 (−1), Cha 10 (+0)</td></tr>'
            . '<tr><th>Rzuty obronne</th><td>Zręczność +5, Inteligencja +3</td></tr>'
            . '<tr><th>Umiejętności</th><td>Oszustwo +4, Zastraszanie +2</td></tr>'
            . '<tr><th>Zmysły</th><td>bierna Percepcja 9</td></tr>'
            . '<tr><th>Wyzwanie</th><td>2 (450 PD)</td></tr>'
            . '</tbody></table>'
            . '<p>Atak wielokrotny: najpierw podstawienie nogi, potem rapier. Rapier +5, 7 (1d8+3) kłute, i dodatkowe 7 (2d6), jeśli cel leży. Podstawienie: istota w zasięgu 5 stóp, test Siły (Atletyka) albo Zręczności (Akrobatyka) przeciw ST 15, inaczej 5 stóp do tyłu i powalenie.</p>'
            . '<p>W karczmie nie walczy na śmierć. Przy połowie życia próbuje uciec. Po kolejnym celnym ciosie się poddaje. Statystyka jest ta sama w <a href="#" class="adv-entry-link" data-entry-id="area-4">epilogu</a>. Towarzysz: <a href="#" class="adv-entry-link" data-entry-id="taben">Taben Vul</a>.</p>';
    }

    private static function figg(): string
    {
        return '<p>Stary awanturnik i przyjaciel rodu Crylla. Spłaca dług wobec Maif, ściągając bogatych znajomych i dowożąc na aukcję prawdziwe magiczne przedmioty. Przyjeżdża w ulewie z czterema ochroniarzami. Moduł nie daje mu kości. Jeśli dojdzie do bójki, traktuj go jak doświadczonego wojownika, a ochroniarzy jak zwykłych strażników, i zakończ to szybko. Tej nocy nie chce walczyć. Chce zostawić skrzynie i wyjść.</p>'
            . '<p>Ma list od Maif: straż ma mu pozwolić złożyć rzeczy w głównej sali. Odpowie na kilka pytań, nie na przesłuchanie. Zauważa, że Jispera nie ma. Zakryty ładunek nazywa „generałem” i mówi, że ktoś na aukcji poluje na starą broń wojskową. Sam nie wie, do czego to służy. Wie, że to antyk i że miał wiele ofert.</p>'
            . '<p>W skrzyniach i workach, w słomie: mikstura siły giganta (stopnia moduł nie podaje — jeśli potrzebujesz liczby, weź hill giant), tarcza +1, różdżka wykrywania magii, <a href="#" class="adv-entry-link" data-entry-id="general">Generał</a> i <a href="#" class="adv-entry-link" data-entry-id="decanter">karafka szlamów</a>. Przebieg dostawy: <a href="#" class="adv-entry-link" data-entry-id="event-2">zdarzenie 2</a>.</p>'
            . '<p>Rano bierze na siebie szkody od Generała i płaci za naprawy. Poległych z drużyny każe wskrzesić u najmocniejszych kleryków miasta. Jeśli uciekli, wystawia nagrodę i chce ich przed sądem. Jeśli przywiozą Jispera żywego z Tiraghin, płaci 100 złota i proponuje dalszą robotę jako ochrona.</p>';
    }

    private static function area1(): string
    {
        return self::img('area-1-auction-hall.jpg', 'Plan Area 1, sala aukcyjna')
            . '<p>Parter. Jedyne wejście z zewnątrz to południowe drzwi, przez <a href="#" class="adv-entry-link" data-entry-id="area-1-1">hol 1-1</a>. Dalej <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galeria 1-2</a>. Po wschodniej ścianie galerii dwa pokoje: <a href="#" class="adv-entry-link" data-entry-id="area-1-3">biuro 1-3</a> i <a href="#" class="adv-entry-link" data-entry-id="area-1-4">pokój straży 1-4</a>.</p>'
            . '<p>Schody w narożniku północno-zachodnim schodzą do <a href="#" class="adv-entry-link" data-entry-id="area-2">piwnicy skarbca</a>. Schody północno-wschodnie, odgrodzone liną i tabliczką „Danger – No Entry”, schodzą do <a href="#" class="adv-entry-link" data-entry-id="area-3">zawalonych kwater</a>.</p>'
            . '<p>Skala na planie: kwadrat to 5 stóp. Północ jest zaznaczona strzałką. Sufit 30 stóp, bez okien, światło z ciągłych płomieni.</p>';
    }

    private static function area11(): string
    {
        return '<p>Za podwójnymi drzwiami sześć postumentów, po trzy przy wschodniej i zachodniej ścianie. Na każdym wazon z różami. W marmurze herb Crylli. Na końcu holu dwie stare zbroje półpłytowe ze tarczami. Przy wschodniej ścianie leży belka okuta żelazem.</p>'
            . '<p>Belka wchodzi w dwa żelazne zaczepy. Trzy akcje, żeby założyć, trzy, żeby zdjąć. Z zewnątrz wyważenie to 150 obrażeń obuchowych w drzwi o KP 11. Z planem: to wąski przedsionek opisany jako 1-1, między drzwiami a galerią.</p>'
            . '<h2>Róże</h2>'
            . '<p>Kto wącha kwiaty, rzuca ST 10 na Kondycję. Porażka: przez 8 godzin utrudnienie na testy i rzuty obronne Wnikliwości przeciw zauroczeniu. Przyroda ST 15 poznaje żółte cętki: uśmiech wyruma. Zapach w promieniu 10 stóp rozluźnia nastrój.</p>'
            . '<h2>Herb</h2>'
            . '<p>Część wardy ze <a href="#" class="adv-entry-link" data-entry-id="area-2-1">skarbca</a>. Kiedy drzwi frontowe są zamknięte, herb lekko świeci. Widać to przy biernej Percepcji 12. Kto wejdzie w to pole albo zacznie tam turę, dostaje 1d4 obrażeń od zimna. Warda milknie, kiedy drzwi są otwarte, albo kiedy <a href="#" class="adv-entry-link" data-entry-id="shirf">Shirf</a> wypowie słowo rozkazu. Kto wie o pułapce, może ją obejść.</p>'
            . '<h2>Zbroje</h2>'
            . '<p>Obie to <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">przyjaciele kupca</a>. Tajemna ST 12 pozwala je rozpoznać. Ożywają, kiedy odpali się warda w przedsionku skarbca. Mają bronić towaru ze skarbca i nie wychodzą z holu. Po ożywieniu blokują przejście.</p>';
    }

    private static function area12(): string
    {
        return '<p>Największa sala, 60 na 100 stóp. Dębowe belki pod sufitem 30 stóp. Pięć żyrandoli wielkości wozu, magiczne świece, jasno w każdym kącie. Dwa bloki eleganckich krzeseł patrzą na zachód, na scenę z kotarami po bokach. Po lewej mównica z herbem. Wokół puste stojaki i stoły, duże i małe. Scena jest o 2 stopy wyżej, wchodzi się dwoma schodkami. Krzeseł jest około trzydziestu. Robota stolarska jest świeża i droga.</p>'
            . '<p>Ze sceny widać przy wschodniej ścianie futrynę z drewnianymi drzwiami, oparte o mur. Drzwi są fałszywe. Jutro Shirf pokaże na nich <a href="#" class="adv-entry-link" data-entry-id="anchor">kotwicę Breedle’a</a>. Tekst, który czyta aukcjoner, jest w <a href="#" class="adv-entry-link" data-entry-id="handout-4">rekwizycie</a>.</p>'
            . '<p>W mównicy leży złożony list od Rimina, majstra od remontu galerii. Daj graczom <a href="#" class="adv-entry-link" data-entry-id="handout-1">obrazek listu</a>. Treść dla ciebie: robotnicy słyszą dudnienie spod cytadeli, dwóch nie chce wracać, galeria będzie na czas, radzi zasypać schody północno-wschodnie.</p>'
            . '<p>Schody północno-zachodnie: <a href="#" class="adv-entry-link" data-entry-id="area-2">Area 2</a>. Schody północno-wschodnie z liną: <a href="#" class="adv-entry-link" data-entry-id="area-3">Area 3</a>. Dwoje drzwi na wschodzie: <a href="#" class="adv-entry-link" data-entry-id="area-1-3">biuro</a> i <a href="#" class="adv-entry-link" data-entry-id="area-1-4">pokój straży</a>. Hol wejściowy: <a href="#" class="adv-entry-link" data-entry-id="area-1-1">1-1</a>.</p>'
            . '<p>Tu Figg zostawia skrzynie. Tu też, rundę po wardzie, wstaje <a href="#" class="adv-entry-link" data-entry-id="general">Generał</a> i zaczyna łamać meble.</p>';
    }

    private static function area13(): string
    {
        return '<p>Mniejszy pokój we wschodnim narożniku. Dwa portrety matron rodu, z rogami jak u Shirfa. Biurko i krzesło naprzeciw stołu. Na stole owoce, pieczywo, jarzyny, dziesięć miedzianych kubków i dwa dzbany wody. To prowiant na noc.</p>'
            . '<p>W górnej szufladzie: dziennik (<a href="#" class="adv-entry-link" data-entry-id="handout-2">notatki dla graczy</a>), pierścień z dwoma kluczami, czyste pergaminy, atrament, pióra. Klucze nie otwierają niczego w cytadeli. Prawdziwe klucze skarbca są przy <a href="#" class="adv-entry-link" data-entry-id="shirf">Shirf</a>.</p>'
            . '<p>Dziennik to lista rzeczy, które chciała kupić albo zdobyć, czasem z dziwnym znaczkiem i z tekstem dla aukcjonera. Inteligencja ST 15: znaczki oznaczają najdziwniejsze pozycje i te leżą teraz w <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbcu</a>.</p>'
            . '<p>Z notatek, bez oddawania graczom całego brudnopisu: spotkanie ze strażą Jispera jutro, zasypać schody północno-wschodnie bo remont za drogi i ktoś mógłby tam zejść, kwiaty i podziękowanie dla matki, pokazać Jisperowi znak wycięty w słupie za cytadelą (może znak złodziei), napisać do Huli o dwóch dodatkowych <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">przyjaciołach kupca</a>.</p>';
    }

    private static function area14(): string
    {
        return '<p>Ciemne marmurowe drzwi otwierają się na zewnątrz, do galerii. W środku dwa stoły, krzesła, dziesiątki wieszaków na płaszcze i stojaków na broń. Tu jutro siedzą ochroniarze gości i pilnują cudzych rzeczy, patrząc na salę.</p>'
            . '<p>Przy zamkniętych drzwiach widać galerię, a z galerii drzwi wyglądają zwyczajnie. Stojaki są puste. Pięć z nich zostało po dawnej cytadeli.</p>'
            . '<p>Percepcja albo Śledztwo ST 15: w spodzie jednego stojaka jest skrytka. Odsunięcie kwadracika drewna odsłania sztylet +1, zapomniany przez dawną załogę. Jeśli oddadzą go Shirf rano, mówi, że to eksponat historyczny, i chce go sprzedać. Drużyna dostaje połowę ceny, 500 złota, po aukcji. Za znalezienie podziel 100 PD. Dodatkowe 25 PD każdej postaci, jeśli sztylet wróci do Shirf.</p>'
            . '<p>W czasie <a href="#" class="adv-entry-link" data-entry-id="event-3">włamania</a> da się tu schować i patrzeć. Generał i tak łazi chaotycznie, a worki złodziei, które upadną na sali, może zniszczyć.</p>';
    }

    private static function merchantsFriend(): string
    {
        return '<p>Dwie sztuki w <a href="#" class="adv-entry-link" data-entry-id="area-1-1">holu</a>. Wyglądają jak poobijane, niemodne półpłyty na żelaznym stojaku o dwóch nogach. Po ożywieniu hałasują przy każdym kroku. Konstrukty, bez charakteru. Właścicielka kazała im pilnować towaru ze skarbca. Nie opuszczają holu. Ruszają, kiedy odpali się warda w <a href="#" class="adv-entry-link" data-entry-id="area-2-1">przedsionku</a>.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>18 (naturalny, tarcza)</td></tr>'
            . '<tr><th>PW</th><td>60 (8d8+24)</td></tr>'
            . '<tr><th>Szybkość</th><td>25 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 14 (+2), Zr 10 (+0), Bd 16 (+3), Int 8 (−1), Mdr 4 (−3), Cha 1 (−5)</td></tr>'
            . '<tr><th>Immunitet</th><td>trucizna; zauroczony, ogłuszony, wyczerpany, przestraszony, otruty</td></tr>'
            . '<tr><th>Zmysły</th><td>bierna Percepcja 7, bez języka</td></tr>'
            . '<tr><th>Wyzwanie</th><td>2 (450 PD) każdy</td></tr>'
            . '</tbody></table>'
            . '<p>Zestrojony strażnik: zawsze wie, czy pilnowany przedmiot jest w 30 stopach, i ma przewagę w walce wręcz przeciw temu, kto go niesie. Atak wielokrotny: pchnięcie i warkot. Pchnięcie +5, zasięg 5 stóp, 4 (1d4+2) obuchowe i 5 stóp do tyłu. Warkot: każda istota w 30 stopach, która niesie pilnowany przedmiot, rzuca ST 13 na Mądrość. Porażka: upuszcza te przedmioty i jest przestraszona do końca swojej następnej tury.</p>'
            . '<p><a href="#" class="adv-entry-link" data-entry-id="general">Generał</a> ich nie atakuje.</p>';
    }

    private static function event2(): string
    {
        return '<p>Zanim złodzieje skończą tunel, na drzwi spadają dwa uderzenia. Głos przez deszcz: to Figg Plyth, prosi otworzyć. Z nim czterech ochroniarzy, worki i skrzynie. Chcą jak najszybciej zejść z ulewy. Czekają, aż drużyna albo Figg wskaże, gdzie to postawić.</p>'
            . '<p>Przez otwarte drzwi widać wóz pod plandeką. Dwóch pcha, jeden kieruje przez hol. Figg każe zdjąć ładunek ostrożnie, plandeka zostaje. Rano przyjdzie do Shirf z opisem. Po wyjściu mają znowu zaryglować drzwi. Szczegóły człowieka: <a href="#" class="adv-entry-link" data-entry-id="figg">Figg</a>.</p>'
            . '<p>Ma list od Maif. Zostawia rzeczy w <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galerii</a> i wychodzi. W środku: mikstura siły giganta, tarcza +1, różdżka wykrywania magii, Generał i karafka szlamów.</p>'
            . '<h2>Jeśli zdejmą plandekę</h2>'
            . '<p>Platforma 10 na 10 stóp, na niej okrągły postument i wielki szklany słój z mętną zieloną cieczą. W cieczy pływa mózg większy niż ludzki, różowy, w szare i czarne cętki. Śledztwo ST 14: rowki w drewnie, platforma może wstać. W szkle są rysy, ale szkło jest grube. Wykrycie magii: zauroczenie. Warda, na przykład ta ze skarbca, budzi go po 1 rundzie. Kości: <a href="#" class="adv-entry-link" data-entry-id="general">Generał</a>.</p>'
            . '<h2>Mokry worek</h2>'
            . '<p>Jeden worek cieknie i stoi za blisko krawędzi stołu. Bierna Percepcja 14 to widzi. Możesz zrzucić go, kiedy chcesz. Kto próbuje go przesunąć, rzuca ST 14 na Zręczność. Porażka: worek spada, szkło pęka, wylewa się <a href="#" class="adv-entry-link" data-entry-id="decanter">karafka szlamów</a> i atakuje najbliższego żywego. To <a href="#" class="adv-entry-link" data-entry-id="vile-ooze">ohydny szlam</a>.</p>';
    }

    private static function vileOoze(): string
    {
        return '<p>Duży szlam, bez charakteru. Wygląda jak sinoszara, lepka glina z jedną macką. Przy ruchu w środku błyska błyskawica. Wylewa się z <a href="#" class="adv-entry-link" data-entry-id="decanter">karafki</a>, jeśli worek Figga spadnie.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>7</td></tr>'
            . '<tr><th>PW</th><td>66 (7d10+28)</td></tr>'
            . '<tr><th>Szybkość</th><td>10 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 12 (+1), Zr 4 (−3), Bd 18 (+4), Int 1 (−5), Mdr 6 (−2), Cha 2 (−4)</td></tr>'
            . '<tr><th>Odporność</th><td>zimno, błyskawice</td></tr>'
            . '<tr><th>Immunitet</th><td>oślepiony, zauroczony, ogłuszony, wyczerpany, przestraszony, powalony</td></tr>'
            . '<tr><th>Zmysły</th><td>ślepowidzenie 60 stóp, za tym ślepy</td></tr>'
            . '<tr><th>Wyzwanie</th><td>2 (450 PD)</td></tr>'
            . '</tbody></table>'
            . '<p>Drętwienie: kto trafi go wręcz, dostaje wstrząs i rzuca ST 14 na Kondycję. Porażka: ogłuszenie do końca następnej tury. Macka +3, zasięg 5 stóp, 8 (2d6+1) obuchowe plus 3 (1d6) od błyskawic.</p>';
    }

    private static function area2(): string
    {
        return self::img('area-2-northwest-cellar.png', 'Plan piwnicy północno-zachodniej')
            . '<p>Schody z północno-zachodniego narożnika <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galerii</a>. Na dole żelazna krata z kłódką. Klucz od Jispera ją otwiera. Inaczej złodziejskie narzędzia, Zręczność ST 12, albo stłuczenie: KP 12, 10 PW.</p>'
            . '<p>Za kratą <a href="#" class="adv-entry-link" data-entry-id="area-2-1">przedsionek 2-1</a>, a w nim żelazna izba <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbca 2-2</a>. Na planie widać drut w poprzek przedsionka, runę przed drzwiami i cztery stoły w skarbcu. Kwadrat to 5 stóp. Obok planu jest rysunek drzwi skarbca.</p>'
            . '<p>Światło przyćmione. Sufit piwnicy 15 stóp.</p>';
    }

    private static function area21(): string
    {
        return '<p>Prostokąt 30 na 35 stóp, bez mebli. Dwie przyćmione latarnie, wschodnia i zachodnia ściana. W głębi mniejsza żelazna izba z jednymi drzwiami. Przed drzwiami świeci runa. Zamek wygląda na taki, który chce dwóch kluczy. Po lewej drewniana tabliczka z wyrzeźbioną dłonią.</p>'
            . '<h2>Drut</h2>'
            . '<p>Śledztwo ST 15 przy ścianach albo podłodze: cienki szew ze wschodu na zachód, między latarniami a schodami. Jeśli drzwi skarbca otworzą się bez właściwych kluczy, żyletkowy drut napina się w poprzek sali. Kto stoi na linii albo później ją przecina, rzuca ST 14 na Zręczność. Porażka: 1d4 cięte. Kto nie widział odpalenia, potrzebuje biernej Percepcji 12, inaczej przy przejściu automatycznie pudłuje rzut. Przecięcie to akcja bronią tnącą. Napięcie daje przecinającemu 1d4 cięte, chyba że uda mu się ST 14 na Zręczność.</p>'
            . '<h2>Warda</h2>'
            . '<p>Odpala się, jeśli drzwi staną otworem, a Shirf nie położyła wcześniej dłoni na tabliczce. Przez cytadelę idzie fala. Każda istota w 10 stopach od runy, po obu stronach drzwi, rzuca ST 13 na Kondycję. Porażka: ogłuszenie na 1 minutę. Rzut można powtarzać na końcu każdej swojej tury. Ta sama fala budzi <a href="#" class="adv-entry-link" data-entry-id="general">Generała</a>, <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">zbroje</a> i, jeśli jeszcze żyją, <a href="#" class="adv-entry-link" data-entry-id="devoted-sentinel">duchy</a>. Zawala też <a href="#" class="adv-entry-link" data-entry-id="area-3-3">kwaterę 3-3</a>.</p>'
            . '<p>Kto postoi tu w ciszy ponad minutę, może wyczuć urywane drżenie. Percepcja ST 12. To kopanie pod skarbcem.</p>';
    }

    private static function area22(): string
    {
        return '<p>Żelazna izba 20 na 15 stóp. Z zewnątrz dwa osobne klucze, które Shirf nosi przy sobie. To nie są klucze z szuflady. Otwarcie wytrychami: dwie osoby naraz, każdy swój test Zręczności ST 20 narzędziami złodziejskimi. Nieudana próba daje wytrychującym 1d8 od błyskawic. Od środka drzwi chodzą lekko. Dlatego włamywacze właśnie tak wyjdą.</p>'
            . '<p>Cztery stoły w rzędzie północ–południe. Dwa mniejsze tuż przy drzwiach, po lewej i prawej, z wycięciami w blatach.</p>'
            . '<p>Na głównych stołach, zanim złodzieje je zgarną: <a href="#" class="adv-entry-link" data-entry-id="censer">kadzielnica Gurga</a>, <a href="#" class="adv-entry-link" data-entry-id="oracle">wyrocznia wieśniaka</a>, <a href="#" class="adv-entry-link" data-entry-id="handkerchief">chustka ukrycia</a>, <a href="#" class="adv-entry-link" data-entry-id="anchor">kotwica Breedle’a</a>, <a href="#" class="adv-entry-link" data-entry-id="scroll">zwój przeniesienia</a>. Na małych stołach: krótki miecz +1, sztylet +1, długi miecz +1, lekka kusza +2, trzy mikstury leczenia, dwie mikstury lepszego leczenia, płaszcz ochrony, pierścień odporności na błyskawice.</p>'
            . '<p>Kiedy drużyna tu wejdzie po <a href="#" class="adv-entry-link" data-entry-id="event-3">włamaniu</a>, stoły są już puste. Towar jest w workach uczniów.</p>';
    }

    private static function area3(): string
    {
        return self::img('area-3-northeast-cellar.jpg', 'Plan piwnicy północno-wschodniej')
            . '<p>Schody z galerii, krata i kłódka jak przy skarbcu. Ten sam klucz Jispera, albo Zręczność ST 12, albo KP 12 i 10 PW. Drewniane drzwi w środku są stare, ale trzymają: KP 15, 18 PW, żadne nie są zamknięte na klucz.</p>'
            . '<p>Dawne kwatery straży. Ciemno, sufit 15 stóp, kurz, świeże ślady stóp. Układ od strony schodów, czyli od zachodu: korytarz <a href="#" class="adv-entry-link" data-entry-id="area-3-1">3-1</a>. Na północy <a href="#" class="adv-entry-link" data-entry-id="area-3-2">kwatera kapitana 3-2</a> i <a href="#" class="adv-entry-link" data-entry-id="area-3-3">kwatera straży 3-3</a>. Na południu <a href="#" class="adv-entry-link" data-entry-id="area-3-4">spiżarnia 3-4</a> i <a href="#" class="adv-entry-link" data-entry-id="area-3-5">zawalone kwatery 3-5</a>, te zasypane gruzem. Kwadrat to 5 stóp.</p>'
            . '<p>Shirf prosi, żeby tu nie schodzić. Rimin w liście radzi zasypać schody. Kilka dni temu robotnicy słyszeli tu zawodzenie i uciekli.</p>';
    }

    private static function area31(): string
    {
        return '<p>Schody kończą się po zachodniej stronie korytarza. Czworo drewnianych drzwi: dwoje na północy, dwoje na południu. Południowo-wschodnie prawie znikają pod gruzem z sufitu. Na środku korytarza szczątki stołu i dwóch ław.</p>'
            . '<p>Kurz na wszystkim, w kurzu świeże ślady. Kto stoi poza <a href="#" class="adv-entry-link" data-entry-id="area-3-5">3-5</a> i uda Percepcję ST 12, słyszy stukanie z południowo-wschodniego narożnika. To kopanie, które schodzi się ze skarbcem, albo duchy poruszające się za drzwiami. Zostaw to niejednoznaczne, dopóki nie zajrzą.</p>';
    }

    private static function area32(): string
    {
        return '<p>Izba 10 na 10 stóp. Zgniłe łóżko, biurko i krzesło zwalone pod północną ścianę. Cztery dębowe stemple od podłogi do sufitu. Robotnicy wyszli w pośpiechu i nie szukali.</p>'
            . '<p>Percepcja ST 14 przy szukaniu: szew w granitowej podłodze. Ostrze podważy płytę. Pod nią skórzany mieszek, 23 złote i dwa oszlifowane agaty po 10 złotych. Za odzyskanie mieszka podziel 75 PD.</p>';
    }

    private static function area33(): string
    {
        return '<p>Jeśli warda skarbca już odpaliła, ta izba jest zasypana. Jeśli nie, jest pusta poza czterema stemplami. W suficie szeroka szczelina, sypie się z niej pył. Sekretów brak. Izba ma 10 na 10 stóp.</p>'
            . '<p>Gwałtowny wstrząs, czyli otwarcie drzwi skarbca, rozrywa szczelinę. Kto jest w środku, rzuca ST 15 na Zręczność. Porażka: 1d10 obuchowych od gruzu, przy ucieczce. Po dwóch rundach izba jest pełna.</p>';
    }

    private static function area34(): string
    {
        return '<p>Rozbite beczki, otwarta skrzynia ze zgniłymi workami po chlebie i sucharach. Sufit cały, rysa do połowy południowej ściany.</p>'
            . '<p>Ruszenie skrzyni wypuszcza zarodniki. Kondycja ST 12, porażka 1d4 od trucizny. Na dnie mieszek z 12 sztukami złota.</p>'
            . '<p>Pod skrzynią klapa i żelazna drabina, 10 stóp w dół, do ciasnego pełzaka na jedną osobę. Na dole ręcznie wykuta nisza: mała skrzynka na starym dzienniku.</p>'
            . '<p>Dziennik to raporty kapitana cytadeli: kto się nadawał do straży, karawany, goście, codzienność fortu. Dla kogoś w Broadgardzie to papier historyczny. Shirf da drużynie połowę ceny aukcyjnej. Cena w notatkach to 20 złotych, więc drużyna dostaje 10.</p>'
            . '<p>Skrzynka drewniana, mosiężne okucia, jeden zatrzask, bez zamka. Śledztwo ST 12: lewe okucie się przesuwa i odsłania przełącznik. Wtedy wieko wstaje. Jeśli ktoś otworzy zatrzask bez przełącznika, z frontu idzie obłok. Kondycja ST 15, porażka 1d4 od trucizny i stan otrucia na godzinę. W środku: 40 złotych, kamień szczęścia (stone of good luck) i cztery oszlifowane kwarce po 5 złotych.</p>';
    }

    private static function area35(): string
    {
        return '<p>Drzwi prawie całe pod gruzem. W niecałą minutę da się odsłonić skobel i uchylić je na kilka cali. Przez szparę widać czysty pokój: dwa łóżka, piękne ręcznie szyte kołdry, między nimi stolik i oprawiona księga.</p>'
            . '<p>To obraz. Jeśli przez 10 minut odgruzują drzwi albo ktoś wciśnie się do środka, obraz gaśnie. Zostaje kurz, wytarte kołdry, przewrócony stolik, kamienie z sufitu. Na każdym łóżku ćwiekowana skóra i krótki miecz. Zbroje i broń wstają. Dwa <a href="#" class="adv-entry-link" data-entry-id="devoted-sentinel">oddane straże</a>, duchy ludzi, którzy zginęli we śnie, kiedy spadł sufit.</p>'
            . '<p>Nie wychodzą z izby, dopóki żywy nie wejdzie w ich pole albo fala z wardy nie potrząśnie cytadelą. Potem chodzą po całym budynku, ale nie za mury.</p>'
            . '<p>Pod złamanym stolikiem leży księga The Lost Mines of Chiselfist. Shirf zapłaci połowę ceny aukcyjnej, a ta cena to 200 złotych, więc drużyna dostaje 100. Inteligencja ST 15 przy lekturze: da się z tego wyciągnąć położenie dawno zaginionej kopalni, haczyk na później.</p>';
    }

    private static function devoted(): string
    {
        return '<p>Dwa duchy w <a href="#" class="adv-entry-link" data-entry-id="area-3-5">3-5</a>. Średnie nieumarłe, neutralne. Wyglądają jak wojownicy w zbroi i z bronią, w której umarli. Twarz smutna, dopóki nie zadadzą pierwszego ciosu. Wtedy jest w nich satysfakcja. Zginęli we śnie, nie na służbie, i zostali, żeby jednak pilnować cytadeli. Kilka dni temu ich zawodzenie usłyszeli robotnicy.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>13 (ćwiekowana skóra)</td></tr>'
            . '<tr><th>PW</th><td>15 (2d10+4)</td></tr>'
            . '<tr><th>Szybkość</th><td>0, latanie 30 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 15 (+2), Zr 13 (+1), Bd 14 (+2), Int 12 (+1), Mdr 8 (−1), Cha 10 (+0)</td></tr>'
            . '<tr><th>Odporność</th><td>obuch, kłucie i cięcie z broni niemagicznej</td></tr>'
            . '<tr><th>Immunitet</th><td>zauroczony, wyczerpany, schwytany, sparaliżowany, skamieniały, otruty, powalony, spętany, nieprzytomny</td></tr>'
            . '<tr><th>Zmysły</th><td>mrokowidzenie 60 stóp, bierna Percepcja 9, język wspólny</td></tr>'
            . '<tr><th>Wyzwanie</th><td>1 (200 PD) każdy</td></tr>'
            . '</tbody></table>'
            . '<p>Ruch bezcielesny: przez istoty i przedmioty jak przez trudny teren. Jeśli kończą turę w przedmiocie, 5 (1d10) obrażeń mocy. Lojalność: przy zejściu do 0 PW rzucają Kondycję ST 10. Sukces: wstają w swojej następnej turze z 1 PW. Krótki miecz +4, zasięg 5 stóp, 5 (1d6+2) obrażeń mocy.</p>';
    }

    private static function event3(): string
    {
        return '<p>Pod skarbcem kopie tylu <a href="#" class="adv-entry-link" data-entry-id="apprentice">uczniów włamywaczy</a>, ilu jest postaci, razy dwa. Mają zwój <a href="#" class="adv-entry-link" data-entry-id="iron-passage">żelaznego przejścia</a> i instrukcję od gildii. Tunel idzie od tygodnia. Nie wiedzą, że wardę założono dwa dni temu. Plan: zabrać broń ze stołów, otworzyć drzwi od środka, ogłuszyć straż i wyjść frontem.</p>'
            . '<p>Wchodzą do skarbca dopiero po dostawie Figga. Ty wybierasz chwilę. Dobrze, kiedy drużyna jest rozdzielona: jeden albo dwóch stoi w przedsionku i dostaje wardę. Albo kiedy są w piwnicy północno-wschodniej i może ich przyłapać zawalenie <a href="#" class="adv-entry-link" data-entry-id="area-3-3">3-3</a>.</p>'
            . '<p>Otwarcie drzwi od środka napina <a href="#" class="adv-entry-link" data-entry-id="area-2-1">drut</a> i puszcza falę. Przyjmij, że połowa złodziei jest na starcie ogłuszona, a połowa dostaje 1d4 cięte od drutu. Nie muszą się pokrywać jeden do jednego. Jeśli walka idzie źle, nie współpracują. Chowają się, uciekają albo łapią zakładnika.</p>'
            . '<p>Fala ożywia <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">zbroje</a> w holu. Blokują wyjście. <a href="#" class="adv-entry-link" data-entry-id="devoted-sentinel">Duchy</a>, jeśli jeszcze są, zawodzą i idą bronić cytadeli przed każdym żywym. Rundę później wstaje <a href="#" class="adv-entry-link" data-entry-id="general">Generał</a> i bije w ruchome cele bez wyboru strony. Złodziei i drużynę traktuje tak samo. Zbroi nie atakuje.</p>'
            . '<p>Czterech uczniów ma magiczną broń ze skarbca. Reszta towaru jest w płóciennych workach, co najmniej jeden przedmiot na osobę. Powalony złodziej upuszcza worek. Jeśli Generał przejdzie po worku, zawartość ginie. Losuj, co w nim było. Kiedy uczniów już nie ma, Generał dalej łazi i niszczy kolejne worki, a z czasem także rzeczy, które przywiózł Figg. Jeśli drużyna na to pozwoli, mogą stracić zapłatę.</p>'
            . '<p>Da się siedzieć w <a href="#" class="adv-entry-link" data-entry-id="area-1-4">pokoju straży</a> i patrzeć przez magiczne drzwi. Generał i tak łazi chaotycznie.</p>';
    }

    private static function apprentice(): string
    {
        return '<p>Ludzie, neutralni źli. Najniższy szczebel gildii. Zbroja pożyczona, broń często wynajęta. Chcą awansu i wzięli robotę ponad swój rozum. Liczba: dwie osoby na każdą postać z drużyny. Kurz, pot, tydzień pod ziemią.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>13 (skóra)</td></tr>'
            . '<tr><th>PW</th><td>5 (1d8+1)</td></tr>'
            . '<tr><th>Szybkość</th><td>30 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 12 (+1), Zr 14 (+2), Bd 12 (+1), Int 7 (−2), Mdr 8 (−1), Cha 7 (−2)</td></tr>'
            . '<tr><th>Języki</th><td>wspólny, złodziejski żargon</td></tr>'
            . '<tr><th>Wyzwanie</th><td>1/8 (25 PD)</td></tr>'
            . '</tbody></table>'
            . '<p>Zwykły krótki miecz +4, 5 (1d6+2) kłute. Czwórka, która zgarnęła broń ze <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbca</a>, używa zamiast tego jednego z tych ataków: krótki miecz +1 (+5, 6 (1d6+3) kłute), sztylet +1 (+5, 5 (1d4+3) kłute), długi miecz +1 (+4, 6 (1d8+2) cięte albo 7 (1d10+2) w dwóch rękach), lekka kusza +2 (+6, 80/320, 8 (1d8+4) kłute).</p>';
    }

    private static function general(): string
    {
        return '<p>Duży konstrukt, bez charakteru. Wyłączony: mózg w słoju na postumencie, platforma 10 na 10 stóp. Włączony: spod platformy wychodzi sześć nóg z drewna i mosiądzu, całość staje na 10 stóp, a z boków wysuwają się dwie kusze. Figg przywiózł go pod plandeką. Wstaje rundę po wardzie.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>13 (naturalny)</td></tr>'
            . '<tr><th>PW</th><td>75 (10d10+20)</td></tr>'
            . '<tr><th>Szybkość</th><td>20 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 16 (+3), Zr 12 (+1), Bd 14 (+2), Int 7 (−2), Mdr 10 (+0), Cha 6 (−3)</td></tr>'
            . '<tr><th>Wrażliwość</th><td>błyskawice</td></tr>'
            . '<tr><th>Odporność</th><td>kłucie, cięcie, zimno</td></tr>'
            . '<tr><th>Immunitet</th><td>oślepiony, zauroczony, ogłuszony, wyczerpany, przestraszony, powalony</td></tr>'
            . '<tr><th>Zmysły</th><td>ślepowidzenie 80 stóp, za tym ślepy, bierna Percepcja 10</td></tr>'
            . '<tr><th>Wyzwanie</th><td>3 (700 PD)</td></tr>'
            . '</tbody></table>'
            . '<p>Rozproszona uwaga: atakiem wielokrotnym chce objąć jak najwięcej różnych celów. Dwa magiczne bełty i psychiczny krzyk. Bełt +4, 80/320, 7 (1d10+2) kłute. Krzyk: stożek 30 stóp, ST 12 na Inteligencję, 7 (2d6) psychiczne.</p>'
            . '<p>Bełty w najbliższy cel bez zasłony. Krzyk tam, gdzie złapie najwięcej osób. Ruch losowy, pełna szybkość, aż do ściany albo istoty. Rzuć 1d4: 1 północ, 2 wschód, 3 zachód, 4 południe. Łamie krzesła, stoły i postumenty. Worki, po których przejdzie, niszczy. Nie tyka <a href="#" class="adv-entry-link" data-entry-id="merchants-friend">przyjaciół kupca</a>.</p>';
    }

    private static function concluding(): string
    {
        return '<p>Po walce sala to połamane stoły, żyrandole, krzesła i kotary. <a href="#" class="adv-entry-link" data-entry-id="jisper">Jisper</a> przychodzi o świcie. Jeśli drzwi są zaryglowane, wali. W środku jest wstrząśnięty i nie bierze winy. Mówi, że Shirf nie wybaczy. Ucieka do miasteczka Tiraghin, do starego przyjaciela, i zaprasza drużynę ze sobą. Wnikliwość ST 12: ucieka przed czymś więcej niż przed tą jedną nocą.</p>'
            . '<p>Jeśli uciekną z nim albo osobno, do Broadgardu już nie wrócą. <a href="#" class="adv-entry-link" data-entry-id="figg">Figg</a> wystawia nagrodę i chce procesu za kradzież i zniszczenie. Jisper i drużyna idą jako wspólnicy rabusiów. Kara jest lżejsza, jeśli nic nie zginęło. Poległych każe wskrzesić po to, żeby ich osądzić i zamknąć.</p>'
            . '<p>Jeśli zostaną, Jisper kręci głową i ucieka sam. Shirf i Figg przychodzą razem, chwilę po nim. Shirf najpierw nie mówi. Chodzi, ogląda szkody, tu i tam łata krzesło sztuczką. Figg pyta o Generała. Kiedy rozumie, co przywiózł, bierze naprawy na siebie. Poległych z drużyny oddaje najmocniejszym klerykom miasta.</p>'
            . '<p>Po chwili Shirf się śmieje. Goście pokochają tę opowieść i ona ją sprzeda. Dziękuje, że nie doszło do kradzieży. Zniszczony towar zbywa. Prosi, żeby zostali i opowiedzieli gościom bitwę. Po mieście idą ballady, coraz mniej zgodne z prawdą. W każdym domu Crylli mają darmowe łóżko.</p>'
            . '<p>Do 240 złota za służbę Shirf dokłada każdej postaci jeden ocalały magiczny przedmiot ze skarbca, do wyboru, zanim zacznie się aukcja. Jeśli pozwolili Generałowi zniszczyć dostawę Figga, tę zapłatę możesz uciąć.</p>'
            . '<p>Zostanie otwiera <a href="#" class="adv-entry-link" data-entry-id="epilogue">epilog</a>.</p>';
    }

    private static function epilogue(): string
    {
        return '<p>Figg uważa, że Jisper działał z gildią. Proponuje wyzwanie: przywieźć go na karę. Drużyna pamięta, że Jisper jedzie do przyjaciela w Tiraghin. Figg daje konie, jeśli ruszą od razu. To pół dnia jazdy.</p>'
            . '<p>Tiraghin: dwanaście domów, przy każdym ogród, 52 osoby, prawie sami rolnicy. Każdy wskaże jedyną karczmę, <a href="#" class="adv-entry-link" data-entry-id="area-4">The Blinded Troll</a>. Nie pomogą i nie przeszkodzą. Chcą, żeby Jisper i <a href="#" class="adv-entry-link" data-entry-id="taben">Taben</a> wyjechali, obojętnie jak.</p>'
            . '<p>Jisper wita drużynę jak wspólników ucieczki. Taben jest podejrzliwy i walczy u jego boku, ale ucieka, jeśli Jisper się podda. Żywego Jispera Figg nagradza 100 sztukami złota i proponuje drużynie dalszą robotę jako ochrona.</p>';
    }

    private static function area4(): string
    {
        return self::img('area-4-blinded-troll.jpg', 'Plan karczmy The Blinded Troll')
            . '<p>Cztery krzywe ściany, dach, któremu nie ufa się na słowo. Trzy małe stoły, dwanaście krzeseł. Drzwi frontowe nie domykają się, zostaje szpara. W kącie dwie beczki i półka z brudnymi szkłami. Barman śpi na krześle podpartym o wschodnią ścianę. Przy jednym stole siedzą <a href="#" class="adv-entry-link" data-entry-id="jisper">Jisper</a> i <a href="#" class="adv-entry-link" data-entry-id="taben">Taben Vul</a>. Odwracają się, kiedy ktoś wchodzi.</p>'
            . '<p>Na planie kwadrat to 5 stóp. Sala jest jedna. Beczki i lada są po stronie, którą na rysunku widać jako otwartą.</p>'
            . '<p>Walka grozi ścianom. Pchnięcie kogoś w mur: 1d4 obrażeń istocie i tyle samo odcinkowi ściany. Każde 10 stóp muru ma 5 PW. Przy 0 odcinek spada. Istoty w 10 stopach rzucają ST 12 na Zręczność. Porażka: 1d6 obuchowych. Pole staje się trudnym terenem.</p>'
            . '<p>Barman od miesiąca sypie Tabenowi do napoju szczyptę korzenia gurva. Ręce mu się trzęsą. Przez 48 godzin, aż mieszanka zejdzie, Taben ma utrudnienie na czary ze składnikiem somatycznym.</p>';
    }

    private static function taben(): string
    {
        return '<p>Młody człowiek, czarownik, neutralny zły. Wierzy w bajki Jispera. Siedzi w Tiraghin ponad miesiąc i czeka na wspólną robotę. Chce wyjechać. Miasto też tego chce. Czarna szata była kiedyś brązowa i nie widziała prania od miesięcy. Błoto na rękach, zardzewiały tępy sztylet za pasem.</p>'
            . '<table><tbody>'
            . '<tr><th>KP</th><td>13 (skóra)</td></tr>'
            . '<tr><th>PW</th><td>19 (3d8+6)</td></tr>'
            . '<tr><th>Szybkość</th><td>20 stóp</td></tr>'
            . '<tr><th>Atrybuty</th><td>S 10 (+0), Zr 12 (+1), Bd 14 (+2), Int 14 (+2), Mdr 8 (−1), Cha 16 (+3)</td></tr>'
            . '<tr><th>Rzuty obronne</th><td>Mądrość +1, Charyzma +5</td></tr>'
            . '<tr><th>Umiejętności</th><td>Tajemną +4, Oszustwo +5</td></tr>'
            . '<tr><th>Zmysły</th><td>bierna Percepcja 9</td></tr>'
            . '<tr><th>Wyzwanie</th><td>1 (200 PD)</td></tr>'
            . '</tbody></table>'
            . '<p>Czarownik 3. poziomu. Charyzma, ST 13, +5 do ataków czarem. Sztuczka: chill touch. Dwa sloty 2. poziomu: hex, hold person, misty step. Atak wielokrotny: dwa odpychające promienie. Promień +5, 120 stóp, 5 (1d10) mocy i pchnięcie o 10 stóp w linii prostej. Sztylet +3, 3 (1d4+1) kłute.</p>'
            . '<p>Dopóki działa korzeń gurva, utrudnienie na czary, które wymagają gestu. Ucieka, jeśli Jisper się podda. Nie zostaje sam przeciw drużynie.</p>';
    }

    private static function items(): string
    {
        return '<p>Dwie dostawy. To, co już leży w <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbcu</a>, i to, co nad ranem nocy przywozi <a href="#" class="adv-entry-link" data-entry-id="figg">Figg</a> do galerii.</p>'
            . '<h2>W skarbcu</h2>'
            . '<ul>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="censer">Kadzielnica Gurga</a>, licytacja od 450 złota.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="oracle">Wyrocznia wieśniaka</a>, od 200.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="handkerchief">Chustka ukrycia</a>, od 750.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="anchor">Kotwica Breedle’a</a>, od 500. Do pokazu są fałszywe drzwi w galerii.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="scroll">Zwój przeniesienia</a>, od 1000. Shirf chce nim schować mównicę.</li>'
            . '<li>Oprócz tego: krótki miecz +1, sztylet +1, długi miecz +1, lekka kusza +2, trzy mikstury leczenia, dwie lepszego leczenia, płaszcz ochrony, pierścień odporności na błyskawice.</li>'
            . '</ul>'
            . '<h2>Od Figga</h2>'
            . '<ul>'
            . '<li>Mikstura siły giganta, tarcza +1, różdżka wykrywania magii.</li>'
            . '<li><a href="#" class="adv-entry-link" data-entry-id="general">Generał</a> i <a href="#" class="adv-entry-link" data-entry-id="decanter">karafka szlamów</a>.</li>'
            . '</ul>'
            . '<p>Tekst, który Shirf czyta gościom, jest na <a href="#" class="adv-entry-link" data-entry-id="handout-4">obrazku</a>. Uczniowie zgarniają skarbiec do worków. Generał niszczy worek, po którym przejdzie. Po walce każda postać, jeśli zostaną i towar ocalał, wybiera jeden magiczny przedmiot ze skarbca jeszcze przed aukcją.</p>';
    }

    private static function censer(): string
    {
        return '<p>Cudowny przedmiot, niezwykły. Małe naczynie, popularne u czarodziejów przy długim ślęczeniu nad księgami. Kto jest w 10 stopach, kiedy pali się w nim dobre kadzidło, ma przewagę na testy Mądrości albo Inteligencji.</p>'
            . '<p>Samo naczynie nie jest rzadkie. To konkretne należało do Gurga Mądrego, gnoma-kapłana, który tłumaczył elficką poezję na język wspólny. Na aukcji Shirf wspomni zbiór iLinga, „A Placid Forest”, i zacznie od 450 złotych.</p>';
    }

    private static function oracle(): string
    {
        return '<p>Cudowny przedmiot, pospolity. Drewniana kostka wielkości pięści. Czarodzieje mają ją za zabawkę. Do pytań, które nie grożą śmiercią, jest użyteczna. Właściciel zadaje pytanie, rzuca kostką i może powtórzyć jeden test Percepcji albo Śledztwa. Potem kostka milczy do świtu.</p>'
            . '<p>Na aukcji: pomoc przy zwykłych decyzjach, start od 200 złotych.</p>';
    }

    private static function handkerchief(): string
    {
        return '<p>Cudowny przedmiot, pospolity. Wystrzępiona, brudna szmatka. Zawinięty w nią jeden przedmiot wielkości pięści jest niewidoczny dla wszystkich poza tym, kto zawinął. Rzucenie albo upuszczenie szmatki, na przykład przy rewizji, nie psuje ukrycia i nie niszczy zawartości. Spalenie albo rozdarcie wypuszcza przedmiot.</p>'
            . '<p>Złodzieje chowają w niej narzędzia albo świeżo ukradzioną błyskotkę. Na aukcji start od 750 złotych. Zwycięzca dostaje instrukcję, jak wyjąć rzecz. Tej instrukcji moduł nie zapisuje. Ustal z drużyną prosty gest, jeśli ją zdobędą.</p>';
    }

    private static function anchor(): string
    {
        return '<p>Cudowny przedmiot, niezwykły. Breloczek w kształcie kotwicy, lekki, mieści się w dłoni. Przytknięty do drzwi albo innego zawiasu nie daje im się otworzyć w żadną stronę. Siła ST 20 łamie ten opór. Przedmiot nie chroni drzwi przed zniszczeniem.</p>'
            . '<p>Jutro Shirf pokaże go na fałszywych drzwiach opartych o wschodnią ścianę <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galerii</a>. Licytacja od 500 złotych. W tekście aukcji jest literówka „biding”. Zostaw ją, jeśli czytasz z kartki. To brzmi jak jej zdenerwowanie, nie jak błąd stołu.</p>';
    }

    private static function scroll(): string
    {
        return '<p>Cudowny przedmiot, pospolity. Rozwinięty wygląda na pusty. Położony tekstem do dołu na jednym przedmiocie, który nie jest żywy, wciąga go. Na pergaminie zostaje rysunek. Transport trwa najwyżej godzinę. Przed końcem trzeba zwój rozwinąć i położyć tekstem do dołu na płaskiej powierzchni. Przedmiot wraca. Jeśli czas minie, przedmiot pojawia się obok zwoju, a zwój się niszczy.</p>'
            . '<p>Shirf chce tym schować ciężką mównicę i przenieść ją do domu. Licytacja od 1000 złotych. To nie jest zwój czaru <a href="#" class="adv-entry-link" data-entry-id="iron-passage">żelaznego przejścia</a>. Tamten mają uczniowie.</p>';
    }

    private static function decanter(): string
    {
        return '<p>Cudowny przedmiot, rzadki. Mała flaszeczka. Pusta, dotknięta do szlamu wielkości dużego albo mniejszego: szlam rzuca ST 15 na Kondycję. Porażka wciąga go do flaszki. W środku nadal musi jeść i może umrzeć z głodu. Wylanie to akcja. Jeśli korek jest zdjęty dłużej niż 10 sekund, czyli 2 rundy, szlam wychodzi sam.</p>'
            . '<p>Figg przywiózł jedną w cieknącym worku. Upadek uwalnia <a href="#" class="adv-entry-link" data-entry-id="vile-ooze">ohydny szlam</a>, który atakuje najbliższego żywego. To nie jest bezpieczny eksponat do postawienia na stole.</p>';
    }

    private static function ironPassage(): string
    {
        return '<p>Transmutacja 4. poziomu, druid i czarodziej. Czas: 1 akcja. Zasięg: dotyk. Składniki: głos, gest i materia (całe lusterko oraz wiórki żelaza). Czas trwania: 1 minuta.</p>'
            . '<p>Przez ten czas jedna żelazna przeszkoda, ściana albo drzwi, mięknie w miejscu dotknięcia. Da się przejść w jedną stronę przez otwór o średnicy 5 stóp. Uczniowie używają zwoju tego czaru, żeby wejść przez podłogę <a href="#" class="adv-entry-link" data-entry-id="area-2-2">skarbca</a> bez zostawiania dziury, którą drużyna zobaczyłaby wcześniej.</p>';
    }

    private static function handout1(): string
    {
        return self::img('handout-1-rimin.png', 'List Rimina do Shirf')
            . '<p>Leży złożony w mównicy <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galerii</a>. Daj graczom obrazek. Rimin kończy remont sali na czas, ale jego ludzie słyszą dudnienie spod podłogi i dwóch nie wraca do roboty. Prosi, żeby zasypać schody północno-wschodnie, czyli zejście do <a href="#" class="adv-entry-link" data-entry-id="area-3">Area 3</a>.</p>';
    }

    private static function handout2(): string
    {
        return self::img('handout-2-notes.png', 'Luźne notatki z biurka Shirf')
            . '<p>Dziennik z szuflady w <a href="#" class="adv-entry-link" data-entry-id="area-1-3">biurze</a>. Pięć kartek, nie ciągły tekst. Spotkanie ze strażą Jispera, zasypanie schodów, kwiaty dla matki, znak wycięty w słupie za cytadelą, list do Huli o dwóch kolejnych zbrojach. Znak z środkowej kartki jest na <a href="#" class="adv-entry-link" data-entry-id="handout-3">pieczęci</a>, którą Shirf chciała pokazać Jisperowi. Sama podejrzewa znak złodziei.</p>';
    }

    private static function handout3(): string
    {
        return self::img('handout-3-seal.png', 'Pieczęć rodu Crylla')
            . '<p>Herb, który straż miejska kojarzy z rodem: profil tieflinga, napis Crylla, obok runa podobna do tej z notatki o słupie za budynkiem. Shirf nosi te same runy na żółtym kapturze. Połóż obrazek na stole, kiedy ktoś ogląda herb w holu, kaptur albo środkową kartkę dziennika.</p>';
    }

    private static function handout4(): string
    {
        return self::img('handout-4-catalog.png', 'Tekst aukcjonera')
            . '<p>Kartka, z której Shirf czyta jutro w południe. Pięć pozycji ze skarbca i widełki startowe: kadzielnica 450, wyrocznia 200, chustka 750, kotwica 500, zwój 1000. W nawiasach są jej didaskalia: kogo wybrać z gości, kiedy poczekać na śmiech, kiedy zniknie mównica. Fałszywe drzwi do kotwicy stoją już w <a href="#" class="adv-entry-link" data-entry-id="area-1-2">galerii</a>. Opisy mechaniki są przy <a href="#" class="adv-entry-link" data-entry-id="items">przedmiotach</a>, nie na tej kartce.</p>';
    }
}
