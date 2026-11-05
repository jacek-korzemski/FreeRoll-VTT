@echo off
:: Copy the repository VERSION file into build\VERSION.
:: Requires: delayed expansion is enabled here. PROMPT_LOCALE=pl selects Polish errors.
setlocal EnableDelayedExpansion

if /i "%PROMPT_LOCALE%"=="pl" (
    set "VER_MISSING=[BLAD] Brak pliku VERSION w katalogu repozytorium."
    set "VER_BAD=[BLAD] VERSION musi byc dodatnia liczba calkowita."
    set "VER_COPY=[BLAD] Nie udalo sie skopiowac VERSION do folderu build."
    set "VER_OK=Wersja paczki:"
) else (
    set "VER_MISSING=[ERROR] VERSION file is missing in the repository root."
    set "VER_BAD=[ERROR] VERSION must be a positive integer."
    set "VER_COPY=[ERROR] Could not copy VERSION into the build folder."
    set "VER_OK=Package version:"
)

if not exist "%~dp0VERSION" (
    echo !VER_MISSING!
    exit /b 1
)

set "PKG_VERSION="
for /f "usebackq tokens=* delims=" %%V in ("%~dp0VERSION") do (
    if not defined PKG_VERSION set "PKG_VERSION=%%V"
)

if "!PKG_VERSION!"=="" (
    echo !VER_BAD!
    exit /b 1
)
if "!PKG_VERSION:~0,1!"=="0" (
    echo !VER_BAD!
    exit /b 1
)
set "NONNUM="
for /f "delims=0123456789" %%A in ("!PKG_VERSION!") do set "NONNUM=%%A"
if defined NONNUM (
    echo !VER_BAD!
    exit /b 1
)

copy /y "%~dp0VERSION" "%~dp0build\VERSION" >nul
if !errorlevel! neq 0 (
    echo !VER_COPY!
    exit /b 1
)

echo !VER_OK! !PKG_VERSION!
exit /b 0
