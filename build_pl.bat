@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>nul

echo.
echo  ========================================
echo   FreeRoll VTT - Skrypt budowania (PL)
echo  ========================================
echo.

where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [BLAD] Node.js nie jest zainstalowany!
    echo Pobierz z: https://nodejs.org/
    pause
    exit /b 1
)

echo  Ten skrypt przeprowadzi Cie przez proces budowania paczki.
echo  Nacisnij ENTER aby uzyc wartosci domyslnych podanych w [nawiasach].
echo.

set "PROMPT_LOCALE=pl"
set "CONFIRM_LABEL=build"
call "%~dp0config-prompts.inc.bat"
if !errorlevel! equ 2 (
    echo.
    echo  Budowanie anulowane.
    pause
    exit /b 0
)

echo.

set "ROOT=%~dp0"
if "!ROOT:~-1!"=="\" set "ROOT=!ROOT:~0,-1!"

echo [1/5] Tworzenie folderu build...
if exist "build" rmdir /s /q "build"
mkdir "build"
mkdir "build\backend"
mkdir "build\backend\data"
mkdir "build\backend\assets"
mkdir "build\backend\assets\map"
mkdir "build\backend\assets\tokens"
mkdir "build\backend\assets\backgrounds"
mkdir "build\backend\assets\papers"
mkdir "build\backend\assets\templates"
mkdir "build\assets"

echo [2/5] Budowanie frontendu (moze chwile potrwac)...
if exist "!ROOT!\frontend\.env" (
    ren "!ROOT!\frontend\.env" ".env.devbackup"
)
(
    echo VITE_BASE_PATH=/vtt/room1/
    echo VITE_API_PATH=backend/api.php
    echo VITE_LANGUAGE=en
    echo VITE_ENABLE_L5R=!ENABLE_L5R!
) > "!ROOT!\frontend\.env"

cd /d "!ROOT!\frontend"

if not exist "node_modules" (
    echo [INFO] Instalowanie zaleznosci npm...
    call npm install
    if !errorlevel! neq 0 (
        echo [BLAD] npm install nie powiodlo sie!
        cd /d "!ROOT!"
        call :restore_frontend_env
        pause
        exit /b 1
    )
)

call npm run build
if !errorlevel! neq 0 (
    echo [BLAD] Budowanie frontendu nie powiodlo sie!
    cd /d "!ROOT!"
    call :restore_frontend_env
    pause
    exit /b 1
)

cd /d "!ROOT!"
call :restore_frontend_env

echo [3/5] Kopiowanie plikow...
xcopy /s /y "frontend\dist\assets\*" "build\assets\" >nul 2>nul
copy /y "backend\api.php" "build\backend\" >nul
copy /y "index.php" "build\" >nul
copy /y "deploy-env.php" "build\" >nul

if exist "backend\assets\templates\*.html" (
    xcopy /y "backend\assets\templates\*.html" "build\backend\assets\templates\" >nul 2>nul
)

(
    echo ^<FilesMatch "^\.env"^>
    echo     Order Allow,Deny
    echo     Deny from all
    echo ^</FilesMatch^>
    echo.
    echo ^<FilesMatch "state\.json$^|rolls\.json$"^>
    echo     Order Allow,Deny
    echo     Deny from all
    echo ^</FilesMatch^>
    echo.
    echo Options -Indexes
) > "build\backend\.htaccess"

(
    echo Order Allow,Deny
    echo Deny from all
) > "build\backend\data\.htaccess"

powershell -Command "'' | Out-File -FilePath 'build\backend\assets\map\.gitkeep' -Encoding ASCII"
powershell -Command "'' | Out-File -FilePath 'build\backend\assets\tokens\.gitkeep' -Encoding ASCII"
powershell -Command "'' | Out-File -FilePath 'build\backend\assets\backgrounds\.gitkeep' -Encoding ASCII"
powershell -Command "'' | Out-File -FilePath 'build\backend\assets\papers\.gitkeep' -Encoding ASCII"
powershell -Command "'' | Out-File -FilePath 'build\backend\data\.gitkeep' -Encoding ASCII"

echo [4/5] Zapisywanie konfiguracji wdrozenia...
powershell -ExecutionPolicy Bypass -File "write-deploy-env.ps1" -OutputPath "build\.env" -Password "%PASSWORD%" -GmPassword "%GM_PASSWORD%" -BasePath "%BASE_PATH%" -Language "%LANGUAGE%" -EnableL5r "%ENABLE_L5R%" -AllowedOrigins "%ALLOWED_ORIGINS%"
if !errorlevel! neq 0 (
    echo [BLAD] Zapis build\.env nie powiodl sie!
    pause
    exit /b 1
)

echo [5/5] Tworzenie plikow .htaccess...
(
    echo Options -Indexes
    echo.
    echo ^<FilesMatch "^\.env$"^>
    echo     Order Allow,Deny
    echo     Deny from all
    echo ^</FilesMatch^>
    echo.
    echo ^<IfModule mod_mime.c^>
    echo     AddType application/javascript .js
    echo     AddType application/javascript .mjs
    echo ^</IfModule^>
) > "build\.htaccess"

echo.
echo  ========================================
echo   BUDOWANIE ZAKONCZONE!
echo  ========================================
echo.
echo   Folder 'build' zawiera gotowa paczke.
echo   Ustawienia wdrozenia sa w build\.env
echo.
echo   Uzyta konfiguracja:
echo     Haslo gracza:     %PASSWORD%
echo     Haslo MG:         %GM_PASSWORD%
echo     Sciezka bazowa:   %BASE_PATH%
echo     Jezyk:            %LANGUAGE%
echo     L5R wlaczone:     %ENABLE_L5R%
echo.
echo   Kolejne kroki:
echo   1. Wgraj zawartosc folderu 'build' na serwer
echo      pod sciezke: %BASE_PATH%
echo   2. Lub uruchom clone.bat aby utworzyc kolejny pokoj
echo   3. Dodaj obrazki do backend/assets/ na serwerze
echo   4. Upewnij sie, ze backend/data/ jest zapisywalny
echo.
echo  ========================================
echo.

pause
exit /b 0

:restore_frontend_env
set "ROOT=%~dp0"
if "!ROOT:~-1!"=="\" set "ROOT=!ROOT:~0,-1!"
if exist "!ROOT!\frontend\.env.devbackup" (
    del "!ROOT!\frontend\.env" >nul 2>nul
    ren "!ROOT!\frontend\.env.devbackup" ".env"
)
goto :eof
