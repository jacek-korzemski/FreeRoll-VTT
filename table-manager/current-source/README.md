# current-source

Tutaj wgraj **zawartość** katalogu `build/` z FreeRoll VTT (wynik `build.bat` / `build_pl.bat`).

Wymagane pliki:

- `index.php`
- `assets/index.js`
- `backend/api.php`
- `deploy-env.php`

Ten katalog nie jest dostępny przez HTTP. Table Manager kopiuje go do `public/vtt/user/<username>/<slug>/` przy tworzeniu stołu i zapisuje świeży `.env` (hasła, `VTT_BASE_PATH`, język).
