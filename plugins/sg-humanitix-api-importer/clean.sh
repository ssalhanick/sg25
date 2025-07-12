#!/bin/bash

echo "========================================"
echo "Humanitix API Importer - Clean Scripts"
echo "========================================"
echo ""
echo "Available commands:"
echo ""
echo "1. composer run clean     - Full cleanup (composer, npm, build, cache, logs, temp)"
echo "2. composer run reset     - Clean + reinstall dependencies + build"
echo "3. composer run fresh     - Clean + fresh install + build"
echo "4. composer run clean:composer - Remove vendor/ and composer.lock"
echo "5. composer run clean:npm      - Remove node_modules/ and package-lock.json"
echo "6. composer run clean:build    - Clean build assets"
echo "7. composer run clean:cache    - Clean cache directories"
echo "8. composer run clean:logs     - Clean log files"
echo "9. composer run clean:temp     - Clean temp files"
echo "10. composer run reset:autoloader - Regenerate composer autoloader"
echo ""
echo "What would you like to do?"
echo ""
read -p "Enter your choice (1-10): " choice

case $choice in
    1)
        echo "Running full cleanup..."
        composer run clean
        ;;
    2)
        echo "Running reset..."
        composer run reset
        ;;
    3)
        echo "Running fresh install..."
        composer run fresh
        ;;
    4)
        echo "Cleaning composer..."
        composer run clean:composer
        ;;
    5)
        echo "Cleaning npm..."
        composer run clean:npm
        ;;
    6)
        echo "Cleaning build..."
        composer run clean:build
        ;;
    7)
        echo "Cleaning cache..."
        composer run clean:cache
        ;;
    8)
        echo "Cleaning logs..."
        composer run clean:logs
        ;;
    9)
        echo "Cleaning temp files..."
        composer run clean:temp
        ;;
    10)
        echo "Regenerating autoloader..."
        composer run reset:autoloader
        ;;
    *)
        echo "Invalid choice. Please run the script again."
        exit 1
        ;;
esac

echo ""
echo "Done!" 