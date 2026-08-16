@echo off
:: Copy the finished build/ package into table-manager\current-source, keeping README.md.
:: Requires: ROOT, delayed expansion. Uses PROMPT_LOCALE (en/pl) for messages.

if /i "%PROMPT_LOCALE%"=="pl" (
    set "CS_SKIP=[INFO] Brak katalogu table-manager — pomijam current-source."
    set "CS_INFO=Kopiowanie paczki do table-manager\current-source..."
    set "CS_ERR=[BLAD] Kopiowanie do current-source nie powiodlo sie!"
    set "CS_OK=Paczka VTT jest w table-manager\current-source (gotowa do tworzenia stolow)."
) else (
    set "CS_SKIP=[INFO] table-manager folder not found — skipping current-source sync."
    set "CS_INFO=Copying package to table-manager\current-source..."
    set "CS_ERR=[ERROR] Copy to current-source failed!"
    set "CS_OK=VTT package is in table-manager\current-source (ready to provision tables)."
)

if not exist "!ROOT!\table-manager\" (
    echo !CS_SKIP!
    exit /b 0
)

echo !CS_INFO!

set "CS=!ROOT!\table-manager\current-source"
if not exist "!CS!" mkdir "!CS!"

set "CS_README=!ROOT!\table-manager\_current-source-README.bak"
if exist "!CS!\README.md" copy /y "!CS!\README.md" "!CS_README!" >nul

for /d %%D in ("!CS!\*") do rmdir /s /q "%%D"
del /q "!CS!\*" >nul 2>nul

xcopy /E /I /Y /Q "!ROOT!\build\*" "!CS!\" >nul
if errorlevel 4 (
    echo !CS_ERR!
    if exist "!CS_README!" del /q "!CS_README!" >nul
    exit /b 1
)

if exist "!CS_README!" (
    copy /y "!CS_README!" "!CS!\README.md" >nul
    del /q "!CS_README!" >nul
)

if not exist "!CS!\backend\vendor\autoload.php" (
    echo !CS_ERR!
    exit /b 1
)

echo !CS_OK!
exit /b 0
