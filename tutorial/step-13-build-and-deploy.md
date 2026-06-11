# Krok 13: Build i wdrożenie

## Cel

- **build.bat** zbiera konfigurację wdrożenia, buduje frontend (npm run build), składa katalog `build/` i zapisuje ustawienia w **`build/.env`**. Hasła **nie trafiają** do kodu frontendu ani do repozytorium.
- **clone.bat** kopiuje gotową paczkę `build/` do nowego folderu i zmienia wyłącznie **`build/.env`** — bez Node.js.
- **Wdrożenie:** upload zawartości `build/` na serwer pod `VTT_BASE_PATH`, zapis na `backend/data/`, opcjonalnie assety w `backend/assets/`.

---

## 1. Plik build/.env

Jeden plik konfiguracyjny w korzeniu paczki (wzór: [`deploy.env.example`](../../deploy.env.example)):

```ini
VTT_PASSWORD=2137
VTT_GM_PASSWORD=admin
VTT_BASE_PATH=/vtt/room1/
VTT_LANGUAGE=en
VTT_ENABLE_L5R=false
ALLOWED_ORIGINS=*
```

- **`index.php`** i **`deploy-env.php`** czytają ten plik w runtime (logowanie, język UI, ścieżka assetów, flaga L5R).
- **`backend/api.php`** czyta ten sam plik (`../.env` w strukturze build) — m.in. `ALLOWED_ORIGINS` dla CORS.
- Frontend dostaje `window.__VTT_CONFIG__` z `index.php` — nie wymaga przebudowy przy zmianie ścieżki ani języka.

---

## 2. Przepływ build.bat

1. **Sprawdzenie Node.js** — wymagane tylko do `npm run build`.
2. **Prompty** — wspólny [`config-prompts.inc.bat`](../../config-prompts.inc.bat) (hasła, ścieżka, język, L5R, originy).
3. **[1/5]** Struktura katalogów `build/`.
4. **[2/5]** `npm install` (jeśli potrzeba) + `npm run build` (Vite z `base: './'` w produkcji).
5. **[3/5]** Kopiowanie: `assets/`, `backend/api.php`, `index.php`, `deploy-env.php`, szablony HTML, `.htaccess`.
6. **[4/5]** [`write-deploy-env.ps1`](../../write-deploy-env.ps1) → `build/.env`.
7. **[5/5]** `build/.htaccess` — ochrona root `.env`, MIME dla JS.

Teksty logowania po polsku/angielsku pochodzą z `VTT_LANGUAGE` w PHP (`deploy-env.php`), nie z osobnych placeholderów w szablonie.

---

## 3. Przepływ clone.bat

1. Wskazanie folderu źródłowego (domyślnie `build/`) i docelowego (domyślnie `build-copy/`).
2. Te same prompty co w build.bat (`config-prompts.inc.bat`).
3. `xcopy` paczki do folderu docelowego.
4. Usunięcie `backend/data/state.json` i `rolls.json` (nowy pokój startuje pusty).
5. Zapis nowego `.env` w folderze docelowym.

**Node.js nie jest wymagany.**

---

## 4. Zawartość build po zakończeniu

- **build/.env** — wszystkie ustawienia wdrożenia
- **build/index.php** + **build/deploy-env.php** — logowanie i wstrzyknięcie configu do JS
- **build/assets/** — index.js, index.css, chunki (wspólne dla wszystkich instancji)
- **build/backend/api.php** — API
- **build/backend/data/** — katalog na state.json (zapisywalny)
- **build/backend/assets/** — map, tokens, backgrounds, papers, templates
- **build/.htaccess** — ochrona `.env`, MIME JS

---

## 5. .htaccess – ochrona wrażliwych plików

W **build/.htaccess**: blokada root `.env`, Options -Indexes, typy MIME dla JS.

W **build/backend/.htaccess**: blokada `.env`, `state.json`, `rolls.json`.

W **build/backend/data/.htaccess**: Deny from all.

---

## 6. Wdrożenie na serwer

1. Wgraj zawartość `build/` pod ścieżkę zgodną z `VTT_BASE_PATH` w `.env`.
2. Ustaw zapisywalność `backend/data/`.
3. Dodaj opcjonalnie obrazy/PDF/szablony do `backend/assets/`.
4. Otwórz stronę, zaloguj się hasłem gracza lub MG.

Zmiana haseł/ścieżki/języka: edytuj `.env` na serwerze lub uruchom `clone.bat` lokalnie.

---

## 7. Aktualizacja wersji VTT

Przy nowej wersji z ZIP-a: podmień `assets/` i `backend/api.php` (oraz `index.php` / `deploy-env.php` jeśli się zmieniły), **zachowaj własny `.env` i `backend/data/`**.

---

## Jak sprawdzić

1. Uruchom `build.bat`. Sprawdź, że `build/.env` zawiera wybrane wartości.
2. Uruchom `clone.bat` z `build/` jako źródłem. Sprawdź nowy folder i nowy `.env`.
3. Wgraj na lokalny PHP pod ścieżką z `.env`. Przetestuj logowanie, API, kości, upload MG.
4. Zmień tylko `VTT_LANGUAGE` w `.env` — interfejs powinien przełączyć się bez `npm run build`.

---

## Uwaga o starych paczkach

Paczki z hasłami wklejonymi w `index.php` (przed tą zmianą) wymagają ponownego `build.bat`.
