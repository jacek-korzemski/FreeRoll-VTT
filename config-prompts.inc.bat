@echo off
:: Shared deployment prompts. Set PROMPT_LOCALE=en|pl before calling.
:: Optional: set CONFIRM_LABEL (e.g. "build" or "clone") for the confirmation question.

if /i "%PROMPT_LOCALE%"=="pl" goto prompts_pl

set "DEFAULT_LANGUAGE=en"
goto prompts_start

:prompts_pl
set "DEFAULT_LANGUAGE=pl"

:prompts_start
echo  ----------------------------------------
echo.

set "PASSWORD="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "PASSWORD=Haslo gracza [2137]: "
) else (
    set /p "PASSWORD=Player password [2137]: "
)
if "%PASSWORD%"=="" set "PASSWORD=2137"

set "GM_PASSWORD="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "GM_PASSWORD=Haslo Mistrza Gry [admin]: "
) else (
    set /p "GM_PASSWORD=Game Master password [admin]: "
)
if "%GM_PASSWORD%"=="" set "GM_PASSWORD=admin"

set "BASE_PATH="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "BASE_PATH=Sciezka bazowa [/vtt/room1/]: "
) else (
    set /p "BASE_PATH=Base path [/vtt/room1/]: "
)
if "%BASE_PATH%"=="" set "BASE_PATH=/vtt/room1/"

:ask_language
set "LANGUAGE="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "LANGUAGE=Jezyk interfejsu (en/pl) [%DEFAULT_LANGUAGE%]: "
) else (
    set /p "LANGUAGE=Language (en/pl) [%DEFAULT_LANGUAGE%]: "
)
if "%LANGUAGE%"=="" set "LANGUAGE=%DEFAULT_LANGUAGE%"
if /i not "%LANGUAGE%"=="en" if /i not "%LANGUAGE%"=="pl" (
    if /i "%PROMPT_LOCALE%"=="pl" (
        echo   Nieprawidlowa opcja. Wpisz 'en' lub 'pl'.
    ) else (
        echo   Invalid option. Please enter 'en' or 'pl'.
    )
    goto ask_language
)

:ask_l5r
set "ENABLE_L5R="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "ENABLE_L5R=Wlaczyc kostki L5R? (true/false) [false]: "
) else (
    set /p "ENABLE_L5R=Enable L5R dice? (true/false) [false]: "
)
if "%ENABLE_L5R%"=="" set "ENABLE_L5R=false"
if /i not "%ENABLE_L5R%"=="true" if /i not "%ENABLE_L5R%"=="false" (
    if /i "%PROMPT_LOCALE%"=="pl" (
        echo   Nieprawidlowa opcja. Wpisz 'true' lub 'false'.
    ) else (
        echo   Invalid option. Please enter 'true' or 'false'.
    )
    goto ask_l5r
)

set "ALLOWED_ORIGINS="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "ALLOWED_ORIGINS=Dozwolone originy (CORS) [*]: "
) else (
    set /p "ALLOWED_ORIGINS=Allowed origins [*]: "
)
if "%ALLOWED_ORIGINS%"=="" set "ALLOWED_ORIGINS=*"

echo.
echo  ----------------------------------------
echo.
if /i "%PROMPT_LOCALE%"=="pl" (
    echo  Podsumowanie konfiguracji:
    echo    Haslo gracza:        %PASSWORD%
    echo    Haslo Mistrza Gry:   %GM_PASSWORD%
    echo    Sciezka bazowa:      %BASE_PATH%
    echo    Jezyk interfejsu:    %LANGUAGE%
    echo    Kostki L5R wlaczone: %ENABLE_L5R%
    echo    Dozwolone originy:   %ALLOWED_ORIGINS%
) else (
    echo  Configuration summary:
    echo    Player password:  %PASSWORD%
    echo    GM password:      %GM_PASSWORD%
    echo    Base path:        %BASE_PATH%
    echo    Language:         %LANGUAGE%
    echo    Enable L5R:       %ENABLE_L5R%
    echo    Allowed origins:  %ALLOWED_ORIGINS%
)
echo.
echo  ----------------------------------------
echo.

if "%CONFIRM_LABEL%"=="" set "CONFIRM_LABEL=proceed"
set "CONFIRM="
if /i "%PROMPT_LOCALE%"=="pl" (
    set /p "CONFIRM=Kontynuowac (%CONFIRM_LABEL%)? (T/n): "
    if /i "!CONFIRM!"=="n" exit /b 2
) else (
    set /p "CONFIRM=Proceed with %CONFIRM_LABEL%? (Y/n): "
    if /i "!CONFIRM!"=="n" exit /b 2
)

exit /b 0
