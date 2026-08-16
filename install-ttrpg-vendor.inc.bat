@echo off
:: Copy TTRPG PHP sources into build\backend and run composer install --no-dev.
:: Requires: ROOT, delayed expansion. Uses PROMPT_LOCALE (en/pl) for messages.

if /i "%PROMPT_LOCALE%"=="pl" (
    set "TV_ERR_COMPOSER=[BLAD] Composer nie jest zainstalowany albo nie ma go w PATH."
    set "TV_ERR_COMPOSER_HINT=Pobierz z: https://getcomposer.org/"
    set "TV_ERR_JSON=[BLAD] Brak backend\composer.json"
    set "TV_ERR_INSTALL=[BLAD] composer install nie powiodl sie!"
    set "TV_ERR_AUTOLOAD=[BLAD] Brak build\backend\vendor\autoload.php po composer install."
    set "TV_INFO=Instalowanie zaleznosci PHP (Composer, --no-dev)..."
) else (
    set "TV_ERR_COMPOSER=[ERROR] Composer is not installed or not in PATH."
    set "TV_ERR_COMPOSER_HINT=Download from: https://getcomposer.org/"
    set "TV_ERR_JSON=[ERROR] Missing backend\composer.json"
    set "TV_ERR_INSTALL=[ERROR] composer install failed!"
    set "TV_ERR_AUTOLOAD=[ERROR] Missing build\backend\vendor\autoload.php after composer install."
    set "TV_INFO=Installing PHP dependencies (Composer, --no-dev)..."
)

if not exist "!ROOT!\backend\composer.json" (
    echo !TV_ERR_JSON!
    exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
    echo !TV_ERR_COMPOSER!
    echo !TV_ERR_COMPOSER_HINT!
    exit /b 1
)

echo !TV_INFO!

if not exist "!ROOT!\build\backend\src" mkdir "!ROOT!\build\backend\src"
xcopy /E /I /Y /Q "!ROOT!\backend\src" "!ROOT!\build\backend\src\" >nul
if exist "!ROOT!\backend\include" (
    if not exist "!ROOT!\build\backend\include" mkdir "!ROOT!\build\backend\include"
    xcopy /E /I /Y /Q "!ROOT!\backend\include" "!ROOT!\build\backend\include\" >nul
)
copy /y "!ROOT!\backend\composer.json" "!ROOT!\build\backend\" >nul
if exist "!ROOT!\backend\composer.lock" copy /y "!ROOT!\backend\composer.lock" "!ROOT!\build\backend\" >nul

cd /d "!ROOT!\build\backend"
call composer install --no-dev --optimize-autoloader --no-interaction
set "TV_CERR=!errorlevel!"
cd /d "!ROOT!"
if not "!TV_CERR!"=="0" (
    echo !TV_ERR_INSTALL!
    exit /b 1
)

if not exist "!ROOT!\build\backend\vendor\autoload.php" (
    echo !TV_ERR_AUTOLOAD!
    exit /b 1
)

(
    echo Order Allow,Deny
    echo Deny from all
) > "!ROOT!\build\backend\vendor\.htaccess"

(
    echo Order Allow,Deny
    echo Deny from all
) > "!ROOT!\build\backend\src\.htaccess"

if exist "!ROOT!\build\backend\include" (
    (
        echo Order Allow,Deny
        echo Deny from all
    ) > "!ROOT!\build\backend\include\.htaccess"
)

exit /b 0
