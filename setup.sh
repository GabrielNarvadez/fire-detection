#!/bin/bash

echo "================================================"
echo "🔥 Fire & Smoke Detection System Setup"
echo "================================================"
echo ""

# Check for Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.8+ first."
    exit 1
fi
echo "✓ Python 3 found"

# Check for pip
if ! command -v pip3 &> /dev/null; then
    echo "❌ pip3 is not installed. Please install pip first."
    exit 1
fi
echo "✓ pip3 found"

# Check for MySQL
if ! command -v mysql &> /dev/null; then
    echo "⚠️  MySQL not found. Please install MySQL 8.0+ or MariaDB."
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
else
    echo "✓ MySQL found"
fi

# Check for PHP
if ! command -v php &> /dev/null; then
    echo "⚠️  PHP not found. Please install PHP 7.4+"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
else
    echo "✓ PHP found"
fi

echo ""
echo "================================================"
echo "📦 Installing Python Dependencies"
echo "================================================"
echo ""

pip3 install -r requirements.txt --break-system-packages

if [ $? -eq 0 ]; then
    echo "✓ Python dependencies installed successfully"
else
    echo "❌ Failed to install Python dependencies"
    exit 1
fi

echo ""
echo "================================================"
echo "🗄️  Database Setup"
echo "================================================"
echo ""
echo "Next steps:"
echo "1. Make sure MySQL is running"
echo "2. Run: mysql -u root -p < database_schema.sql"
echo "3. Update database credentials in:"
echo "   - fire_detection_system.py (lines 16-21)"
echo "   - api.php (lines 12-15)"
echo ""
read -p "Do you want to set up the database now? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Enter MySQL root password:"
    mysql -u root -p < database_schema.sql
    if [ $? -eq 0 ]; then
        echo "✓ Database created successfully"
    else
        echo "❌ Database setup failed"
    fi
fi

echo ""
echo "================================================"
echo "⚙️  Configuration"
echo "================================================"
echo ""
echo "Please update the following files with your credentials:"
echo ""
echo "1. fire_detection_system.py"
echo "   Lines 16-21: Database configuration"
echo ""
echo "2. api.php"
echo "   Lines 12-15: Database configuration"
echo ""
echo "3. fire-detection-dashboard.html"
echo "   Line ~960: API_URL (if API is not on localhost:8000)"
echo ""
read -p "Press Enter to continue..."

echo ""
echo "================================================"
echo "🚀 Starting the System"
echo "================================================"
echo ""
echo "To run the system, open 3 terminals:"
echo ""
echo "Terminal 1 - Python Detection:"
echo "  python3 fire_detection_system.py"
echo ""
echo "Terminal 2 - PHP API:"
echo "  php -S localhost:8000 api.php"
echo ""
echo "Terminal 3 - Dashboard:"
echo "  php -S localhost:8080 fire-detection-dashboard.html"
echo "  OR just open fire-detection-dashboard.html in browser"
echo ""
echo "Then open: http://localhost:8080/fire-detection-dashboard.html"
echo ""
echo "================================================"
echo "✅ Setup Complete!"
echo "================================================"
echo ""
echo "Need help? Check README.md for detailed instructions."
echo ""