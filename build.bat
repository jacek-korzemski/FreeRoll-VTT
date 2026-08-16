@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>nul

echo.
echo  ========================================
echo   FreeRoll VTT - Build Script
echo  ========================================
echo.

where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js is not installed!
    echo Download from: https://nodejs.org/
    pause
    exit /b 1
)

where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Composer is not installed!
    echo Download from: https://getcomposer.org/
    pause
    exit /b 1
)

echo  This script will guide you through the build process.
echo  Press ENTER to use default values shown in [brackets].
echo.

set "PROMPT_LOCALE=en"
set "CONFIRM_LABEL=build"
call "%~dp0config-prompts.inc.bat"
if !errorlevel! equ 2 (
    echo.
    echo  Build cancelled.
    pause
    exit /b 0
)

echo.

set "ROOT=%~dp0"
if "!ROOT:~-1!"=="\" set "ROOT=!ROOT:~0,-1!"

echo [1/6] Creating build folder...
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

echo [2/6] Building frontend (this may take a while)...
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
    echo [INFO] Installing npm dependencies...
    call npm install
    if !errorlevel! neq 0 (
        echo [ERROR] npm install failed!
        cd /d "!ROOT!"
        call :restore_frontend_env
        pause
        exit /b 1
    )
)

call npm run build
if !errorlevel! neq 0 (
    echo [ERROR] Frontend build failed!
    cd /d "!ROOT!"
    call :restore_frontend_env
    pause
    exit /b 1
)

cd /d "!ROOT!"
call :restore_frontend_env

echo [3/6] Copying files...
xcopy /s /y "frontend\dist\assets\*" "build\assets\" >nul 2>nul
copy /y "backend\api.php" "build\backend\" >nul
copy /y "index.php" "build\" >nul
copy /y "deploy-env.php" "build\" >nul

if exist "backend\assets\templates\*.html" (
    xcopy /y "backend\assets\templates\*.html" "build\backend\assets\templates\" >nul 2>nul
)

echo [4/6] Installing TTRPG backend (Composer)...
call "%~dp0install-ttrpg-vendor.inc.bat"
if !errorlevel! neq 0 (
    pause
    exit /b 1
)

(
    echo ^<FilesMatch "^\.env"^>
    echo     Order Allow,Deny
    echo     Deny from all
    echo ^</FilesMatch^>
    echo.
    echo ^<FilesMatch "state\.json$^|rolls\.json$^|ttrpg\.sqlite$^|ttrpg\.sqlite-(journal^|wal^|shm)$^|secrets\.json$"^>
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

echo [5/6] Writing deployment configuration...
powershell -ExecutionPolicy Bypass -File "write-deploy-env.ps1" -OutputPath "build\.env" -Password "%PASSWORD%" -GmPassword "%GM_PASSWORD%" -BasePath "%BASE_PATH%" -Language "%LANGUAGE%" -EnableL5r "%ENABLE_L5R%" -AllowedOrigins "%ALLOWED_ORIGINS%"
if !errorlevel! neq 0 (
    echo [ERROR] Writing build\.env failed!
    pause
    exit /b 1
)

echo [6/6] Creating .htaccess files...
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

call "%~dp0sync-current-source.inc.bat"
if !errorlevel! neq 0 (
    pause
    exit /b 1
)

echo.
echo  ========================================
echo   BUILD COMPLETE!
echo  ========================================
echo.
echo   The 'build' folder contains the package.
echo   Deployment settings are in build\.env
echo.
echo   Configuration used:
echo     Player password: %PASSWORD%
echo     GM password:     %GM_PASSWORD%
echo     Base path:       %BASE_PATH%
echo     Language:        %LANGUAGE%
echo     L5R enabled:     %ENABLE_L5R%
echo.
echo   Next steps:
echo   1. Upload contents of 'build' folder to server
echo      at location: %BASE_PATH%
echo   2. Or create a table in Table Manager (current-source is already updated)
echo   3. Or run clone.bat to create another room from this build
echo   4. Add images to backend/assets/ on the server
echo   5. Ensure backend/data/ folder is writable
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
