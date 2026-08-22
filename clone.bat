@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>nul

echo.
echo  ========================================
echo   FreeRoll VTT - Clone Script
echo  ========================================
echo.
echo  Copy an existing build package and apply new deployment settings.
echo  No Node.js required.
echo.

set "SOURCE_FOLDER="
set /p "SOURCE_FOLDER=Source folder [build]: "
if "%SOURCE_FOLDER%"=="" set "SOURCE_FOLDER=build"

if not exist "%SOURCE_FOLDER%\index.php" (
    echo [ERROR] Source folder "%SOURCE_FOLDER%" is not a valid build package.
    echo         Run build.bat first, or point to an existing build folder.
    pause
    exit /b 1
)

if not exist "%SOURCE_FOLDER%\assets\index.js" (
    echo [ERROR] Missing frontend assets in "%SOURCE_FOLDER%\assets\".
    echo         Run build.bat first to create a complete package.
    pause
    exit /b 1
)

set "DEST_FOLDER="
set /p "DEST_FOLDER=Destination folder [build-copy]: "
if "%DEST_FOLDER%"=="" set "DEST_FOLDER=build-copy"

if /i "%SOURCE_FOLDER%"=="%DEST_FOLDER%" (
    echo [ERROR] Source and destination folders must be different.
    pause
    exit /b 1
)

echo.
echo  Press ENTER to use default values shown in [brackets].
echo.

set "PROMPT_LOCALE=en"
set "CONFIRM_LABEL=clone"
call "%~dp0config-prompts.inc.bat"
if !errorlevel! equ 2 (
    echo.
    echo  Clone cancelled.
    pause
    exit /b 0
)

echo.
echo [1/3] Copying package from "%SOURCE_FOLDER%" to "%DEST_FOLDER%"...
if exist "%DEST_FOLDER%" rmdir /s /q "%DEST_FOLDER%"
xcopy /E /I /Q "%SOURCE_FOLDER%" "%DEST_FOLDER%" >nul
if !errorlevel! geq 4 (
    echo [ERROR] Copy failed!
    pause
    exit /b 1
)

echo [2/3] Resetting session data...
if exist "%DEST_FOLDER%\backend\data\state.json" del /q "%DEST_FOLDER%\backend\data\state.json"
if exist "%DEST_FOLDER%\backend\data\rolls.json" del /q "%DEST_FOLDER%\backend\data\rolls.json"
if not exist "%DEST_FOLDER%\backend\data" mkdir "%DEST_FOLDER%\backend\data"
powershell -Command "if (-not (Test-Path '%DEST_FOLDER%\backend\data\.gitkeep')) { '' | Out-File -FilePath '%DEST_FOLDER%\backend\data\.gitkeep' -Encoding ASCII }"

echo [3/3] Writing deployment configuration...
powershell -ExecutionPolicy Bypass -File "write-deploy-env.ps1" -OutputPath "%DEST_FOLDER%\.env" -Password "%PASSWORD%" -GmPassword "%GM_PASSWORD%" -BasePath "%BASE_PATH%" -Language "%LANGUAGE%" -EnableL5r "%ENABLE_L5R%" -AllowedOrigins "%ALLOWED_ORIGINS%" -ColorTemplate "%COLOR_TEMPLATE%"
if !errorlevel! neq 0 (
    echo [ERROR] Writing %DEST_FOLDER%\.env failed!
    pause
    exit /b 1
)

echo.
echo  ========================================
echo   CLONE COMPLETE!
echo  ========================================
echo.
echo   New package: %DEST_FOLDER%
echo   Settings file: %DEST_FOLDER%\.env
echo.
echo   Configuration used:
echo     Player password: %PASSWORD%
echo     GM password:     %GM_PASSWORD%
echo     Base path:       %BASE_PATH%
    echo     Language:        %LANGUAGE%
    echo     Color template:  %COLOR_TEMPLATE%
    echo     L5R enabled:     %ENABLE_L5R%
echo.
echo   Next steps:
echo   1. Upload contents of '%DEST_FOLDER%' to your server
echo      at location: %BASE_PATH%
echo   2. Ensure backend/data/ folder is writable
echo.
echo  ========================================
echo.

pause
exit /b 0
