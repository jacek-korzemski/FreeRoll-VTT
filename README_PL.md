# Darmowy wirtualny stół do gier RPG o otwartym kodzie źródłowym

Możesz stworzyć swój stół i grać tutaj: http://vtt.angrymaya.pl/

Pamiętam tylko, proszę - nie używaj swojego głównego adresu e-mail, nie używaj hasła którego używasz gdzie indziej. Jestem tylko hobbystą w trakcie nauki i nie mogę zagwarantować, że moja znajomość security wystarczy na potrzeby produkcyjne. Traktujcie to bardziej jako darmową zabawkę. 

## FreeRoll VTT – prosty, samodzielny wirtualny stół

FreeRoll VTT to **lekki Virtual TableTop**, który budujesz raz, a potem hostujesz na **zwykłym serwerze PHP/Apache (lub nginx)** – bez Dockera czy działającego non‑stop Node’a.

Możesz wdrożyć **jeden pokój**, wgrywając katalog `build/`, albo uruchomić **Table Manager** (`table-manager/`), w którym gracze zakładają konta i tworzą własne stoły (każdy stół to kopia zbudowanej paczki VTT).

### Najważniejsze funkcje

- **Sceny i zarządzanie mapą**
  - wiele scen z **dodawaniem / zmianą nazwy / duplikowaniem / usuwaniem**
  - natychmiastowe **przełączanie sceny** dla wszystkich podłączonych graczy
  - stan przechowywany per scena: tło, mgła wojny, elementy mapy, tokeny

- **Tła map**
  - obrazy tła przygotowane pod **siatkę 64×64 px**
  - tła wrzucasz do `backend/assets/backgrounds`
  - w locie możesz **przesuwać, skalować i resetować** tło (pozycja i zoom)

- **Elementy mapy i tokeny**
  - osobne przeglądarki dla **elementów mapy** (`backend/assets/map`) i **tokenów** (`backend/assets/tokens`)
  - przeciąganie i upuszczanie na siatkę z przyciąganiem do pól
  - **sprawdzanie kolizji**, żeby przypadkiem nie ułożyć kilku tokenów/elementów na jednym polu
  - **gumka elementów**, która szybko usuwa obiekty z mapy

- **Mgła wojny (oparta o siatkę)**
  - mgła wojny trzymana jako bitmapa, w podziale na pola siatki
  - **tryb edycji tylko dla Mistrza Gry**, pędzel kołowy z regulowanym rozmiarem
  - tryby **odkrywania / zakrywania**, plus akcje **Odkryj wszystko** i **Zakryj wszystko**
  - opcjonalny **podgląd dla MG z przezroczystością 50%**, żeby widzieć jednocześnie mapę i mgłę

- **Rzuty kośćmi**
  - standardowe kości: **d4, d6, d8, d10, d12, d20, d100**, dowolna liczba kości
  - **nazwa gracza** zapisywana w przeglądarce
  - modyfikator do rzutu oraz **czytelna historia rzutów** (współdzielona przez backend)
  - opcjonalny moduł **Legend of the Five Rings (L5R)** z poprawnymi kośćmi pierścienia/umiejętności, eksplodującymi wynikami oraz podsumowaniem (sukces / okazja / stres)

- **Notatniki i szablony**
  - do **3 równoczesnych notatników WYSIWYG** na użytkownika
  - możliwość **zapisu/odczytu danych jako JSON** i eksportu do HTML
  - wsparcie dla **szablonów HTML z serwera** (`backend/assets/templates/*.html`) – idealne na karty postaci
  - centralny **kontekst szablonów notatek**, z którego mogą korzystać makra (odczyt pól)

- **Edytor makr**
  - wizualny edytor **makr do rzutów**, z wyrażeniami typu `2d6+@str`
  - makra potrafią czytać wartości z **nazwanych pól w szablonach notatek** (np. statystyki z karty postaci)
  - sortowanie, edycja, import/eksport makr oraz odpalanie ich prosto do historii rzutów

- **Panel czytnika PDF**
  - lista i otwieranie PDF‑ów z serwera z katalogu `backend/assets/papers`
  - dodatkowo **lokalne PDF‑y w przeglądarce**, przechowywane tylko po stronie klienta
  - leniwe ładowanie kolejnych stron dla dobrej wydajności przy dużych podręcznikach

- **Narzędzie ping (przywołanie uwagi)**
  - Mistrz Gry może **wysłać pinga w wybrane pole siatki**; widok wszystkich graczy przewinie się w to miejsce z animacją
  - opcja **wyczyszczenia pinga**, żeby nowi gracze nie byli przekierowywani do starego znacznika

- **Upload materiałów z poziomu aplikacji**
  - panel uploadu dla MG, który pozwala wysłać **tokeny, elementy mapy, tła, szablony HTML i pliki PDF** bezpośrednio na serwer
  - podstawowa walidacja typów plików i rozmiarów, komunikaty o błędach

- **Prosta autoryzacja i tryb MG**
  - **hasło gracza** i **hasło MG**, definiowane w `build/.env`
  - strona logowania obsługiwana przez `index.php`, z tekstami zależnymi od `VTT_LANGUAGE`
  - backend rozróżnia **MG vs gracz** i ogranicza wrażliwe akcje (upload, edycja mgły, zmiany scen itp.)

- **Wielojęzyczny interfejs (en / pl)**
  - wszystkie teksty trzymane w `frontend/src/lang/translations.json`
  - język wybierany w `build/.env` (`VTT_LANGUAGE=en` lub `pl`)

---

## Wymagania

### Do zbudowania aplikacji (maintainerzy)

- **Node.js 18+** (`https://nodejs.org/`)
- **Composer** (`https://getcomposer.org/`) — instaluje warstwę TTRPG Manager do `build/backend/vendor`
- **Windows** (jeśli chcesz używać dostarczonych skryptów `build.bat` / `build_pl.bat`)

### Do utworzenia kolejnego pokoju z gotowej paczki (bez Node)

- **Windows** z PowerShell (dla `clone.bat`)
- istniejący katalog `build/` (z `build.bat` lub pobranego ZIP-a)

### Serwer do uruchomienia paczki

- **PHP 7.4+**
- Serwer WWW z obsługą:
  - plików `.htaccess` (Apache) **lub**
  - równoważnej konfiguracji w nginx (przepisywanie adresów + ochrona `.env` i plików danych)

Wygenerowany katalog `build` to statyczne assety + mały backend PHP, więc działa na większości tanich hostingów PHP.

---

## Szybki start na Windows

### 1. Zbuduj paczkę (wymaga Node.js)

W katalogu głównym projektu uruchom **jeden** z poniższych skryptów:

- `build.bat` – interaktywne budowanie (komunikaty po angielsku, domyślny język UI: **en**)
- `build_pl.bat` – interaktywne budowanie (komunikaty po polsku, domyślny język UI: **pl**)

Skrypt zbiera ustawienia wdrożenia (hasła, ścieżka, język, L5R, CORS), uruchamia `npm run build`, `composer install --no-dev` w backendzie, składa katalog `build/` i kopiuje paczkę do `table-manager/current-source/`.

Wszystkie ustawienia wdrożenia trafiają do **`build/.env`** (patrz [`deploy.env.example`](deploy.env.example)). Paczka frontendu jest niezależna od ścieżki i języka – `index.php` czyta `.env` w runtime.

### 2. Sklonuj kolejny pokój (bez Node.js)

Jeśli masz już katalog `build/` i potrzebujesz kolejnej instancji (inna ścieżka, hasła lub język):

```bat
clone.bat
```

Skrypt kopiuje paczkę do nowego folderu i zapisuje świeży `build/.env`. Dane sesji (`backend/data/state.json`) są resetowane.

### 3. Wgraj paczkę na serwer

- **Wgraj zawartość katalogu `build/`** (nie sam folder) do docelowego katalogu na serwerze zgodnego z `VTT_BASE_PATH` z `.env`.
- Na serwerze umieść własne materiały w:
  - `backend/assets/map/` – elementy mapy
  - `backend/assets/tokens/` – tokeny
  - `backend/assets/backgrounds/` – tła map
  - `backend/assets/papers/` – pliki PDF dla czytnika
  - `backend/assets/templates/` – szablony HTML dla notatników / kart postaci
- Upewnij się, że katalog `backend/data/` jest **zapisywalny** przez użytkownika serwera WWW, np. na Linuksie:
  - `chmod 755 backend/data/` (lub bardziej liberalnie – zależnie od hostingu)

Następnie otwórz w przeglądarce skonfigurowany adres (np. `https://twojadomena.pl/vtt/room1/`) i zaloguj się wybranym hasłem gracza / MG.

Aby później zmienić hasła lub ścieżkę, edytuj `build/.env` na serwerze (albo uruchom lokalnie `clone.bat` i wgraj ponownie).

---

## Table Manager (wiele stołów, konta użytkowników)

[Table Manager](table-manager/) to panel Laravel + Livewire + SQLite. Użytkownicy się rejestrują, zakładają do **3 stołów** na konto i grają pod `/vtt/user/<username>/<slug>/`. Każdy stół to pełna kopia paczki VTT z `table-manager/current-source/` (ten sam pomysł co `clone.bat`).

Wybierz ten wariant, gdy chcesz jeden hosting z wieloma pokojami zamiast ręcznego wgrywania `build/` na każdą sesję.

### Dodatkowe wymagania (Table Manager)

- **PHP 8.3+** (samodzielny pokój z `build/` może działać na starszym PHP)
- **Composer**
- **Node.js 18+** (tylko do zbudowania CSS/JS panelu: `npm run build` w `table-manager/`)
- Apache z `mod_rewrite` albo nginx (patrz niżej)
- Katalogi zapisywalne przez serwer WWW: `table-manager/database/`, `table-manager/storage/`, `table-manager/public/vtt/`

### 1. Przygotuj paczkę źródłową VTT

Table Manager **nie** odpala `npm run build` ani `composer install` przy tworzeniu stołu. Paczka VTT musi być już złożona.

1. W **katalogu głównym** repozytorium FreeRoll odpal `build.bat` albo `build_pl.bat` (Node.js + Composer).
2. Skrypt zapisuje `build/` **i** kopiuje go do `table-manager/current-source/` (zostawia `current-source/README.md`).
3. W `current-source/` powinny być m.in.:
   - `index.php`
   - `assets/index.js`
   - `backend/api.php`
   - `backend/vendor/autoload.php` (Composer, integracja TTRPG)
   - `backend/src/Ttrpg/`
   - `backend/include/telemetry.php`
   - `deploy-env.php`

Flaga L5R jest dziedziczona z `current-source/.env` (`VTT_ENABLE_L5R`). Żeby nowe stoły miały L5R, zbuduj paczkę z włączonym L5R.

Jeśli pominiesz automatyczne kopiowanie, wklej **zawartość** katalogu `build/` (nie sam folder) do `table-manager/current-source/`.

### 2. Zainstaluj Table Manager

```bash
cd table-manager
composer install
copy .env.example .env          # Linux/macOS: cp .env.example .env
php artisan key:generate
```

W `.env` zostaw `DB_CONNECTION=sqlite` (domyślnie). Następnie:

```bash
php artisan migrate
npm install
npm run build
```

Ustaw `APP_URL` na publiczny adres strony (np. `https://twojadomena.pl`), a na produkcji `APP_ENV=production` i `APP_DEBUG=false`.

### 3. Skieruj serwer WWW na `table-manager/public`

**DocumentRoot musi wskazywać na `table-manager/public`**, nie na korzeń repozytorium i nie na pojedynczy folder `build/` VTT.

| Adres | Co to jest |
|-------|------------|
| `https://twojadomena.pl/` | Logowanie / rejestracja / lista stołów |
| `https://twojadomena.pl/vtt/user/<username>/<slug>/` | Gotowy stół VTT |
| `https://twojadomena.pl/admin` | Panel admina (tylko z paska adresu — bez linku w UI gracza) |

Laravelowe `.htaccess` serwuje istniejące pliki pod `/vtt/` jak zwykłe PHP/statyki, więc sklonowane stoły działają jak samodzielny upload `build/`.

**Szkic nginx:**

```nginx
root /sciezka/table-manager/public;
index index.php;

location /vtt/ {
    try_files $uri $uri/ $uri/index.php?$query_string;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Na nginx dodatkowo zablokuj HTTP do `.env` i `backend/data/` (Apache korzysta z `.htaccess` dołączonych do paczki VTT).

### 4. Utwórz stół (jako gracz)

1. Wejdź na stronę, **zarejestruj się** (imię, **username** w URL — później się nie zmienia, e-mail, hasło).
2. Po zalogowaniu otwórz **Stoły**.
3. Podaj nazwę stołu, **hasło gracza**, **hasło MG**, język interfejsu (`pl` / `en`) i kliknij **Utwórz stół**.
4. Wyślij grupie adres stołu i hasło gracza. Hasło MG zostaw Mistrzowi Gry.

Hasła VTT i język można potem zmienić z dashboardu. Usunięcie stołu kasuje rekord w bazie i pliki na dysku.

### 5. Skonfiguruj panel admina

Logowanie admina **nie** jest w bazie danych. Przed produkcją edytuj [`table-manager/config/admin.php`](table-manager/config/admin.php):

```php
'username' => 'admin',
'password' => 'freeroll-admin',  // zmień to
```

Potem otwórz w przeglądarce **`/admin`** (wpisz adres ręcznie; w publicznym UI nie ma do niego linku).

Panel pokazuje wszystkie stoły, **hasła VTT** (gracz/MG, nie hasła kont), wgrane pliki, `state.json` / `rolls.json` oraz telemetrię (logowania, sesje obecności, interakcje).

Telemetrię zapisuje instancja VTT w `backend/data/telemetry/`. Stoły z **starej** paczki (sprzed tych plików w buildzie) nie będą miały statystyk, dopóki nie przebudujesz `current-source/` i nie **utworzysz nowego stołu** (albo nie skopiujesz do istniejącego katalogu nowych `index.php`, `backend/api.php` i `backend/include/telemetry.php`).

### Podgląd lokalny

```bash
cd table-manager
php artisan serve
```

Wbudowany serwer PHP serwuje `public/`, więc `/vtt/user/...` działa, o ile `current-source/` jest wgrane.

Więcej szczegółów: [`table-manager/README.md`](table-manager/README.md).

---

## Dla deweloperów

### Backend w trybie deweloperskim (bez logowania, CORS dla localhost)

```bash
cd backend
php -S localhost:8080
```

Uruchamia to wbudowany serwer PHP pod `http://localhost:8080` i włącza skróty deweloperskie w `api.php` (pominięcie logowania, możliwość wymuszenia roli MG przez `?gm=1` lub cookie `dev_gm`).

### Frontend z serwerem deweloperskim Vite

```bash
cd frontend
npm install
npm run dev
```

Domyślnie frontend działa pod adresem `http://localhost:5173` i łączy się z backendem pod `http://localhost:8080/backend/api.php`. W razie potrzeby możesz to zmienić w lokalnej konfiguracji (`.env` / `config`).

