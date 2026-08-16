# FreeRoll Table Manager

Panel do zakładania i kasowania stołów FreeRoll VTT. Laravel + Livewire + SQLite.
Każdy stół to kopia paczki z `current-source/` (ten sam mechanizm co `clone.bat` w repozytorium VTT).

## Wymagania

- PHP 8.3+
- Composer
- Node.js 18+ (tylko do zbudowania CSS/JS panelu: `npm run build`)
- Apache z `mod_rewrite` (albo nginx — patrz niżej)
- Gotowa paczka VTT z `build.bat` w katalogu głównym repozytorium FreeRoll

## Instalacja

```bash
cd table-manager
composer install
copy .env.example .env   # Windows: copy  |  Linux: cp .env.example .env
php artisan key:generate
```

Upewnij się, że w `.env` jest `DB_CONNECTION=sqlite`. Utwórz plik bazy i migracje:

```bash
php artisan migrate
npm install
npm run build
```

Katalogi `database/` oraz `storage/` i `public/vtt/` muszą być zapisywalne przez użytkownika serwera WWW.

## Paczka źródłowa (`current-source/`)

1. W katalogu głównym FreeRoll odpal `build.bat` (albo `build_pl.bat`). Wymaga **Node.js** i **Composer**.
2. Skrypt sam wgrywa gotową paczkę do `table-manager/current-source/` (z vendorami PHP i warstwą TTRPG). Możesz też ręcznie skopiować zawartość `build/` (nie sam folder).
3. W `current-source/` muszą być m.in.:
   - `index.php`
   - `assets/index.js`
   - `backend/api.php`
   - `backend/vendor/autoload.php`
   - `backend/src/Ttrpg/`
   - `backend/include/telemetry.php`
   - `deploy-env.php`

Table Manager **nie** odpala `npm run build` ani `composer install` przy tworzeniu stołu. Paczka musi być już złożona. Flaga L5R jest dziedziczona z `current-source/.env` (`VTT_ENABLE_L5R`). Żeby stoły miały L5R, zbuduj paczkę z włączonym L5R.

## Apache

**DocumentRoot musi wskazywać na `table-manager/public`.**

Panel: `https://domena.pl/` (logowanie, rejestracja, lista stołów).  
Stoły: `https://domena.pl/vtt/user/<username>/<slug>/`

Laravelowe `.htaccess` serwuje istniejące pliki i katalogi bez przepisywania do `index.php`, więc sklonowane stoły działają jak zwykłe paczki PHP.

## nginx (szkic)

```nginx
root /ścieżka/table-manager/public;
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

Zablokuj odczyt `.env` i `backend/data/*.json` (paczka VTT ma własne `.htaccess`; na nginx trzeba to powtórzyć w `location`).

## Użytkownicy i stoły

- Rejestracja: imię, **username** (slug w URL, bez zmian później), e-mail, hasło.
- Maks. **3 stoły** na konto.
- Przy tworzeniu: nazwa, hasło gracza, hasło MG, język (`pl` / `en`).
- Hasła można potem zmienić w panelu (zapis do SQLite i do `.env` stołu).
- Usunięcie stołu kasuje katalog i rekord.

Adres stołu: `/vtt/user/{username}/{slug}/`  
To samo trafia do `VTT_BASE_PATH` w `.env` instancji.

## Panel admina

Dostęp **tylko** przez bezpośredni adres `/admin` (brak linku w UI gracza). Login i hasło są w [`config/admin.php`](config/admin.php), nie w bazie — zmień je przed produkcją.

Domyślnie: `admin` / `freeroll-admin`.

Panel pokazuje stoły, hasła VTT (gracz/MG), pliki uploadów, `state.json` i analitykę (logowania, sesje obecności, interakcje). Telemetria powstaje w instancji VTT (`backend/data/telemetry/`) po zbudowaniu nowej paczki i utworzeniu stołu.

## Dev lokalnie

```bash
php artisan serve
```

Wbudowany serwer PHP serwuje pliki z `public/`, więc stoły pod `/vtt/user/...` też działają, o ile `current-source/` jest wgrane.
