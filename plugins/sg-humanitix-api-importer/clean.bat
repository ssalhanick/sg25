@echo off
echo ========================================
echo Humanitix API Importer - Clean Scripts
echo ========================================
echo.
echo Available commands:
echo.
echo 1. composer run clean     - Full cleanup (composer, npm, build, cache, logs, temp)
echo 2. composer run reset     - Clean + reinstall dependencies + build
echo 3. composer run fresh     - Clean + fresh install + build
echo 4. composer run clean:composer - Remove vendor/ and composer.lock
echo 5. composer run clean:npm      - Remove node_modules/ and package-lock.json
echo 6. composer run clean:build    - Clean build assets
echo 7. composer run clean:cache    - Clean cache directories
echo 8. composer run clean:logs     - Clean log files
echo 9. composer run clean:temp     - Clean temp files
echo 10. composer run reset:autoloader - Regenerate composer autoloader
echo.
echo What would you like to do?
echo.
set /p choice="Enter your choice (1-10): "

if "%choice%"=="1" (
    echo Running full cleanup...
    composer run clean
) else if "%choice%"=="2" (
    echo Running reset...
    composer run reset
) else if "%choice%"=="3" (
    echo Running fresh install...
    composer run fresh
) else if "%choice%"=="4" (
    echo Cleaning composer...
    composer run clean:composer
) else if "%choice%"=="5" (
    echo Cleaning npm...
    composer run clean:npm
) else if "%choice%"=="6" (
    echo Cleaning build...
    composer run clean:build
) else if "%choice%"=="7" (
    echo Cleaning cache...
    composer run clean:cache
) else if "%choice%"=="8" (
    echo Cleaning logs...
    composer run clean:logs
) else if "%choice%"=="9" (
    echo Cleaning temp files...
    composer run clean:temp
) else if "%choice%"=="10" (
    echo Regenerating autoloader...
    composer run reset:autoloader
) else (
    echo Invalid choice. Please run the script again.
    pause
    exit /b 1
)

echo.
echo Done!
pause 