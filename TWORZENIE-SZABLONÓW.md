# Przewodnik Tworzenia Szablonów

Jak tworzyć interaktywne karty postaci i własne szablony dla FreeRoll VTT.

Szablony to zwykłe pliki HTML ze specjalnymi atrybutami `data-*`. Do podstawowych potrzeb wystarczy sam HTML — pola tekstowe, checkboxy i przyciski rzutu kości działają od razu. W bardziej zaawansowanych kartach możesz dodać blok `<style>` (automatycznie ograniczony do danej instancji szablonu) oraz blok `<script>` (sandboxowane API `vtt` do reaktywnych obliczeń, np. modyfikatorów cech). Umieść swoje pliki `.html` w `backend/assets/templates/` i pojawią się w wyborze szablonu w panelu notatek.

---

## Szybki start

Minimalny działający szablon:

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Mój Szablon</title>
</head>
<body>

  <table class="tpl-table">
    <tr>
      <td><strong>Imię:</strong> <input data-field="name" type="text" class="plain wide"></td>
    </tr>
    <tr>
      <td><strong>PŻ:</strong> <input data-field="hp" type="text" class="box sm"></td>
    </tr>
  </table>

</body>
</html>
```

Zawartość znacznika `<title>` staje się tytułem notatki w chwili wczytania szablonu.

---

## Edytowalne pola

Każde edytowalne pole używa atrybutu `data-field` z **unikalną nazwą**. Ta nazwa jest używana wewnętrznie do zapisu i odtworzenia wartości.

### Pole tekstowe

```html
<input data-field="character_name" type="text" class="plain">
```

### Textarea (wieloliniowe)

```html
<textarea data-field="backstory" class="plain wide" rows="5"></textarea>
```

### Checkbox

```html
<input data-field="has_proficiency" type="checkbox">
```

### Wartości domyślne

Użyj standardowego atrybutu `value`. Zostanie on użyty przy pierwszym wczytaniu szablonu:

```html
<input data-field="prof_bonus" type="text" class="box xs" value="+2">
```

---

## Style pól

Dostępne są trzy warianty wizualne. Dodaj je jako klasy CSS:

### `.plain` — Niewidoczne pole

Tekst pojawia się naturalnie, jakby był częścią dokumentu. Bez ramki, bez tła. Przy najechaniu i fokusie pojawia się delikatne podkreślenie.

```html
<input data-field="name" type="text" class="plain">
```

Najlepsze dla: imion, opisów, notatek — wszędzie tam, gdzie chcesz płynny tekst inline.

### `.box` — Pole z ramką

Standardowe pole z widoczną ramką i wyśrodkowanym tekstem.

```html
<input data-field="armor_class" type="text" class="box">
```

Najlepsze dla: wartości liczbowych, modyfikatorów, małych danych.

### `.circle` — Okrągła bańka statystyki

Okrąg 36x36 px z pogrubionym, wyśrodkowanym tekstem. Wygląda jak klasyczna bańka statystyki RPG.

```html
<input data-field="strength" type="text" class="circle">
```

Najlepsze dla: cech, klasy pancerza, kluczowych statystyk.

### Modyfikatory rozmiaru

Połącz z klasą rozmiaru:

| Klasa   | Szerokość | Zastosowanie |
|---------|-----------|--------------|
| `.xs`   | 36px      | Pojedyncza liczba: modyfikator, premia |
| `.sm`   | 60px      | Krótka wartość: PŻ, poziom |
| `.wide` | 100%      | Pełna szerokość: imię, opis |

Przykłady:

```html
<input data-field="str_mod" type="text" class="box xs">
<input data-field="hp_current" type="text" class="box sm">
<input data-field="character_name" type="text" class="plain wide">
<textarea data-field="notes" class="plain wide" rows="6"></textarea>
```

---

## Struktura tabeli

Użyj `class="tpl-table"` na tabelach, by dostać poprawną stylizację. Dostępne klasy komórek:

```html
<table class="tpl-table">
  <tr>
    <td class="section-header">Tytuł sekcji</td>      <!-- Czerwony nagłówek akcentowy -->
  </tr>
  <tr>
    <td class="section-header-sm">Pod-nagłówek</td>   <!-- Subtelny szary nagłówek -->
  </tr>
  <tr>
    <td class="center">Wyśrodkowana zawartość</td>
    <td class="right">Zawartość do prawej</td>
  </tr>
</table>
```

---

## Przyciski rzutu kośćmi

Dodaj przycisk z atrybutem `data-roll`, by stworzyć inline'owy rzut kośćmi. Kliknięcie rzuca kośćmi i wysyła wynik do panelu kości (widoczny dla wszystkich graczy).

### Podstawowy rzut

```html
<button data-roll="d20" class="roll-btn">🎲</button>
```

Rzuca k20. Wspierane kości: `d4`, `d6`, `d8`, `d10`, `d12`, `d20`, `d100`.

### Rzut wieloma kośćmi

```html
<button data-roll="2d6" class="roll-btn">🎲</button>
```

### Rzut ze stałym modyfikatorem

```html
<button data-roll="d20+5" class="roll-btn">🎲</button>
```

### Rzut z wartością z pola

Odwołaj się do dowolnego pola przez `@nazwa_pola`. Aktualna wartość zostanie sparsowana jako liczba i dodana do rzutu:

```html
<input data-field="str_mod" type="text" class="box xs" value="+3">
<button data-roll="d20+@str_mod" class="roll-btn">🎲</button>
```

Jeśli `str_mod` zawiera `+3`, rzut staje się d20+3.

### Modyfikator warunkowy (biegłość)

Użyj składni `+@wartość?@warunek`. Wartość jest dodawana **tylko jeśli** checkbox warunku jest zaznaczony:

```html
<input data-field="athletics" type="text" class="box xs" value="+3">
<input data-field="athletics_prof" type="checkbox">
<input data-field="prof_bonus" type="text" class="box xs" value="+2">

<button data-roll="d20+@athletics+@prof_bonus?@athletics_prof" class="roll-btn">🎲</button>
```

To oznacza:
- Zawsze rzuć d20
- Zawsze dodaj wartość `@athletics` (+3)
- Dodaj `@prof_bonus` (+2) **tylko jeśli** `@athletics_prof` jest zaznaczony

Wynik: d20+3 (bez biegłości) lub d20+5 (z biegłością).

### Etykieta rzutu

Użyj `data-roll-label`, by nazwać rzut w historii kości:

```html
<button data-roll="d20+@athletics" data-roll-label="Atletyka" class="roll-btn">🎲</button>
```

W historii kości pojawi się jako: **NazwaGracza (Atletyka): d20 [15] +3 = 18**

### Dynamiczne etykiety

Etykiety też mogą odwoływać się do wartości pól:

```html
<input data-field="weapon_name" type="text" class="plain" value="Długi miecz">
<button data-roll="d20+@atk_mod" data-roll-label="Atak: @weapon_name" class="roll-btn">🎲</button>
```

Pokaże: **NazwaGracza (Atak: Długi miecz): d20 [12] +5 = 17**

### Zawartość przycisku

Tekst przycisku może być dowolny — emoji, tekst lub jedno i drugie:

```html
<button data-roll="d20" class="roll-btn">🎲</button>
<button data-roll="d20" class="roll-btn">🎲 Rzuć</button>
<button data-roll="d20" class="roll-btn">🎲 Rzut przeciw śmierci</button>
```

---

## Referencja wyrażeń rzutu

| Wyrażenie | Znaczenie |
|---|---|
| `d20` | Rzuć jedną k20 |
| `2d6` | Rzuć dwie k6 |
| `d20+5` | Rzuć k20, dodaj 5 |
| `d20+@str_mod` | Rzuć k20, dodaj wartość z pola `str_mod` |
| `d20+@str_mod+@prof?@str_prof` | Rzuć k20, dodaj `str_mod`, dodaj `prof` tylko jeśli `str_prof` jest zaznaczony |
| `2d6+@dmg_mod` | Rzuć 2k6, dodaj wartość z pola `dmg_mod` |

---

## Pełny przykład: wiersz umiejętności

Typowa umiejętność D&D 5e z przełącznikiem biegłości i rzutem jednym kliknięciem:

```html
<tr>
  <td>
    <input data-field="skill_stealth_prof" type="checkbox"> Skradanie <small>(Zrc)</small>
  </td>
  <td class="right">
    <input data-field="skill_stealth" type="text" class="box xs">
  </td>
  <td class="center">
    <button
      data-roll="d20+@skill_stealth+@prof?@skill_stealth_prof"
      data-roll-label="Skradanie"
      class="roll-btn">🎲</button>
  </td>
</tr>
```

Jak to działa:
1. Gracz wpisuje swój modyfikator skradania (np. `+2`) w pole `skill_stealth`
2. Gracz zaznacza checkbox `skill_stealth_prof` jeśli ma biegłość
3. Pole `prof` (zdefiniowane gdzie indziej) trzyma premię z biegłości (np. `+3`)
4. Kliknięcie 🎲 rzuca: d20 + 2 + 3 (z biegłością) lub d20 + 2 (bez)

---

## Własna stylizacja (CSS na szablon)

Możesz dostarczyć CSS razem z szablonem. Wstaw blok `<style>` w `<head>` (lub gdziekolwiek w `<body>`) i używaj zwykłych selektorów CSS — runtime przepisuje je tak, żeby trafiały tylko w elementy **wewnątrz tej instancji szablonu**.

### Po co scopowanie

Dwie notatki załadowane z tego samego szablonu (lub z dwóch różnych szablonów) żyją obok siebie na tej samej stronie. Bez scopowania `.tpl-table { background: red }` z jednego szablonu pomalowałoby każdą tabelę w aplikacji. Runtime poprzedza każdy selektor `[data-vtt-scope="<unikalne-id>"]`, więc style nigdy nie wyciekają poza swoją instancję.

### Przykład

```html
<head>
  <meta charset="UTF-8">
  <title>Mój Szablon</title>
  <style>
    .tpl-table { background: #fbf6ec; border-color: #c9b88e; }
    .section-header {
      background: linear-gradient(180deg, #6a2c2c, #4a1a1a);
      color: #f4e7c0;
    }
    /* Wyróżnij pola obliczane automatycznie */
    input[data-field$="_mod"] {
      background: #efe3c4;
      font-weight: 700;
    }
  </style>
</head>
```

Wewnętrznie `.tpl-table { background: #fbf6ec }` staje się `[data-vtt-scope="note-1"] .tpl-table { background: #fbf6ec }` dla jednej notatki, `[data-vtt-scope="note-2"] .tpl-table { ... }` dla drugiej itd.

### Reguły scopowania

| Typ reguły | Scopowane? | Uwagi |
|---|---|---|
| Selektory tagów/klas/id | tak | `.tpl-table`, `#hp`, `input[data-field="x"]` |
| Listy selektorów po przecinku | tak (każda część) | `.a, .b` → obie poprzedzone |
| `:root`, `html`, `body` | tak — mapują na korzeń scope'a | użyj do ustawienia tła, fontu itd. dla całej instancji |
| `@media`, `@supports`, `@container`, `@layer` | tak (rekurencyjnie) | wewnętrzne reguły są scopowane |
| `@keyframes`, `@font-face`, `@import`, `@charset` | nie scopowane | nazwy pozostają globalne; odwołuj się do nich po nazwie |

### Wskazówki

- Globalny CSS aplikacji (np. `.note-template-renderer .tpl-table` z `App.css`) ładuje się pierwszy; twoje scopowane reguły nadpisują domyślne, bo selektor scopowany jest bardziej specyficzny.
- Unikaj `!important` — czysta specyficzność wystarcza dzięki prefiksowi `[data-vtt-scope="…"]`.
- Nie próbuj uciec ze swojego scope'a (żadnego `:root :host`, żadnego `html { ... }` w nadziei na globalny zasięg). Wszystkie reguły są zamknięte w korzeniu szablonu.

---

## Własne skrypty (reaktywne zachowanie)

Szablony mogą zawierać blok `<script>`, który uruchamia się w niewielkim sandboxie. Skrypt dostaje obiekt `vtt`, udostępniający jedyny bezpieczny sposób odczytu/zapisu pól i reagowania na zmiany. W ten sposób szablon `dnd_5e.html` automatycznie liczy modyfikatory cech ze score'ów.

### Gdzie umieścić skrypt

Gdziekolwiek w `<body>` (zwykle na końcu, po znacznikach):

```html
<body>
  <!-- ...tabele, pola, przyciski... -->

  <script>
    function abilityModifier(score) {
      var n = parseInt(score, 10);
      if (isNaN(n)) return '';
      var m = Math.floor((n - 10) / 2);
      return (m >= 0 ? '+' : '') + m;
    }

    function refresh(ability) {
      vtt.setField(ability + '_mod', abilityModifier(vtt.getField(ability + '_score')));
    }

    var ABILITIES = ['str','dex','con','int','wis','cha'];

    vtt.onMount(function () {
      ABILITIES.forEach(refresh);
    });

    vtt.onFieldChange(function (name) {
      var match = name.match(/^(str|dex|con|int|wis|cha)_score$/);
      if (match) refresh(match[1]);
    });
  </script>
</body>
```

### Referencja API `vtt`

| Element | Typ | Opis |
|---|---|---|
| `vtt.scopeId` | `string` | Unikalne id tej instancji (np. `note-1`, `token-abc`). Przydatne do debugowania przez `console.log`. |
| `vtt.root` | `HTMLElement` | Kontener DOM, który trzyma wyrenderowany szablon. Używaj `vtt.root.querySelector(...)` jeśli potrzebujesz bezpośredniego dostępu do DOMu; nigdy nie używaj `document.querySelector` (wyciekałoby między instancjami). |
| `vtt.getField(name)` | `string \| boolean` | Aktualna wartość `data-field`. String dla text/textarea, boolean dla checkboxa. |
| `vtt.setField(name, value)` | `void` | Aktualizuje pole. Zapisuje do inputa w DOM ORAZ zapisuje do storage'u. Stringi, liczby albo boolean (dla checkboxa). |
| `vtt.onMount(cb)` | `void` | `cb()` uruchamia się raz po zamontowaniu szablonu i wczytaniu pól z zapisanych danych. Dobre do początkowych obliczeń. |
| `vtt.onFieldChange(cb)` | `void` | `cb(name, value)` uruchamia się przy każdej edycji pola przez użytkownika. NIE odpala się dla zmian wywołanych przez `vtt.setField` (brak nieskończonych pętli). |
| `vtt.onDestroy(cb)` | `void` | `cb()` uruchamia się przy odmontowaniu szablonu (np. gdy gracz wyczyści notatkę, zmieni szablon, zamknie panel tokenu). Użyj do anulowania timerów, observerów itd. |
| `vtt.fields` | `object` | Read-only snapshot wartości w momencie mount. Do bieżących wartości używaj `getField(name)`. |

### Co skrypty mogą robić

- Automatycznie liczyć pola pochodne (modyfikatory, sumy, premie do RO).
- Reagować na przełączniki checkboxów (np. przeliczać sumy gdy zmieni się biegłość).
- Dodawać własne zachowania DOM na `vtt.root` (np. tooltipy, plakietki) — pamiętaj, że DOM jest twój tylko wewnątrz `vtt.root`.

### Czego skrypty nie mogą / nie powinny robić

- **Brak żądań zewnętrznych, brak globali.** Twój skrypt uruchamia się jako `new Function('vtt', code)(vtt)` w originie strony. Ogranicz się do API `vtt` i standardowych built-inów JS.
- **Brak `<script src="…">`.** Skrypty zewnętrzne są ignorowane — wykonują się tylko inline'owe bloki `<script>…</script>`.
- **Brak sięgania do innych szablonów.** Trzymaj się `vtt.root` / `vtt.getField` / `vtt.setField`. Inne instancje mają własny scope.
- **Brak założeń o stanie React.** Runtime trzyma DOM, localStorage i stan React'a w synchronizacji przez API `vtt`. Nie grzeb po wewnętrznościach React'a.

### Cykl życia

```
wczytaj szablon → zamontuj DOM → odtwórz zapisane pola → uruchom <script> → vtt.onMount() →
   ↓ użytkownik wpisuje coś w polu
   ↓ DOM się aktualizuje → odpalają się callbacki vtt.onFieldChange(name, value)
   ↓ twój callback może wywołać vtt.setField(...) → DOM + storage zaktualizowane, BEZ rekurencyjnego onFieldChange
   ...
odmontowanie → odpalają się callbacki vtt.onDestroy() → DOM zostaje zburzony
```

### Błędy

Jeśli twój skrypt rzuci wyjątek, błąd ląduje w konsoli przeglądarki z prefiksem `[template:<scopeId>]`, a reszta szablonu działa dalej. To samo dotyczy pojedynczych callbacków `onFieldChange`/`onMount`/`onDestroy` — jeden błędny handler nie psuje pozostałych.

### Uwaga o zaufaniu

Szablony z `backend/assets/templates/` są traktowane jako **trusted content**: ich bloki `<script>` wykonują dowolny JavaScript w originie aplikacji. Wgrywaj tylko szablony z zaufanych źródeł (własne pliki, kuratorowane paczki MG).

---

## Zapis i wczytywanie

### Jak dane są przechowywane

- Struktura szablonu (HTML) + wartości pól (JSON) są zapisywane razem w localStorage
- Każdy slot notatki ma niezależny storage
- Dane przeżywają sesje przeglądarki

### Opcje eksportu (przycisk 💾)

W trybie szablonu przycisk zapisu oferuje dwie opcje:

- **Zapisz dane (.json)** — Pełny backup: HTML szablonu + wszystkie wartości pól. Można wczytać z powrotem, by odtworzyć wszystko dokładnie.
- **Eksportuj jako HTML** — Generuje samodzielny plik HTML ze wszystkimi wartościami wpieczonymi w środek. Dobre do wydruku albo udostępnienia poza VTT. Przyciski rzutu są ukryte w wyeksportowanym pliku.

### Wczytywanie szablonów

Przycisk wczytywania (📂) oferuje:

- **Lokalny plik** — Wczytaj `.html` (szablon lub notatnik) albo `.json` (zapisane dane szablonu) z dysku
- **Szablon z serwera** — Wybierz szablon z `backend/assets/templates/`

Przy wczytaniu pliku `.json` wszystkie zapisane wcześniej wartości pól zostają odtworzone. Przy wczytaniu szablonu `.html` pola startują puste (lub z domyślnymi wartościami z `value`).

---

## Wdrożenie

1. Stwórz swój plik szablonu `.html`
2. Umieść go w `backend/assets/templates/`
3. Pojawi się automatycznie w wyborze szablonu
4. Nazwa pliku staje się nazwą wyświetlaną (podkreślniki i myślniki zamieniane na spacje, pierwsza litera wielka)

Przykład: `dnd_5e.html` pojawi się jako **"Dnd 5e"** w wyborze.

---

## Wskazówki

- **Nazwy pól muszą być unikalne** w obrębie całego szablonu. Używaj prefiksów typu `skill_`, `save_`, `atk1_`, by uniknąć kolizji.
- **Najpierw HTML, skrypty na końcu.** Pełną kartę można zbudować używając tylko `data-field` i `data-roll`. Dodawaj `<style>` i `<script>` tylko gdy potrzebujesz tematyzacji albo pól obliczanych — są opcjonalne.
- **Testuj lokalnie.** Otwórz swój plik `.html` w przeglądarce, by sprawdzić strukturę przed wdrożeniem. Inputy, checkboxy i twój własny CSS będą działać; tylko przyciski rzutu i API skryptów `vtt` wymagają runtime'u VTT.
- **Używaj `<small>` na podpowiedzi** typu skróty cech: `Skradanie <small>(Zrc)</small>`.
- **Textarea na długie teksty.** Używaj `<textarea>` z `rows="N"` zamiast inputów tekstowych dla list ekwipunku, opisu postaci, notatek itp.
- **Dwie instancje, dwa scope'y.** Ten sam szablon można wczytać do wielu notatek lub paneli tokenu jednocześnie — każda instancja ma własny `data-vtt-scope`, własne style, własną instancję `vtt`. Nie współdziel stanu przez globale; używaj wartości `data-field`.
