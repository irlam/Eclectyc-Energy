#!/bin/bash
#
# Dependency Installation Script for Eclectyc Energy
#
# This script installs all required dependencies for the application
# Run this after deploying the application files to the server
#
# Usage:
#   bash deployment/install-dependencies.sh
#   OR
#   ./deployment/install-dependencies.sh

set -e  # Exit on error

echo "==================================================="
echo "Eclectyc Energy - Dependency Installation"
echo "==================================================="
echo ""

# Detect project root (parent of deployment directory)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

echo "Project root: $PROJECT_ROOT"
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "ERROR: Composer is not installed!"
    echo ""
    echo "Please install composer first:"
    echo "  curl -sS https://getcomposer.org/installer | php"
    echo "  sudo mv composer.phar /usr/local/bin/composer"
    echo ""
    exit 1
fi

echo "✓ Composer found: $(composer --version)"
echo ""

# Navigate to project root
cd "$PROJECT_ROOT"

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo "ERROR: composer.json not found in $PROJECT_ROOT"
    echo "Make sure you're running this from the correct directory"
    exit 1
fi

echo "✓ composer.json found"
echo ""

# Install dependencies
echo "Installing dependencies..."
echo "This may take a few minutes..."
echo ""

# Determine environment
if [ -f ".env" ]; then
    if grep -q "APP_ENV=production" .env; then
        echo "Installing production dependencies (no dev packages)..."
        composer install --no-dev --optimize-autoloader --no-interaction
    else
        echo "Installing all dependencies (including dev packages)..."
        composer install --optimize-autoloader --no-interaction
    fi
else
    echo "No .env file found, installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

echo ""
echo "==================================================="
echo "✓ Dependencies installed successfully!"
echo "==================================================="
echo ""

# Verify vendor directory
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    echo "✓ vendor/autoload.php created successfully"
else
    echo "ERROR: vendor/autoload.php not found after installation!"
    exit 1
fi

# Check and create required directories
echo ""
echo "Checking required directories..."

REQUIRED_DIRS=("logs" "storage" "storage/imports" "exports")

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "  Creating $dir..."
        mkdir -p "$dir"
    else
        echo "  ✓ $dir exists"
    fi
done

# Set permissions
echo ""
echo "Setting permissions..."
chmod -R 755 "$PROJECT_ROOT" 2>/dev/null || echo "  (Could not set 755 on all files - may need sudo)"
chmod -R 777 "$PROJECT_ROOT/logs" 2>/dev/null || echo "  (Could not set 777 on logs - may need sudo)"
chmod -R 777 "$PROJECT_ROOT/storage" 2>/dev/null || echo "  (Could not set 777 on storage - may need sudo)"

echo "✓ Permissions set"

# Final verification
echo ""
echo "==================================================="
echo "Installation Complete!"
echo "==================================================="
echo ""
echo "Next steps:"
echo "  1. Ensure .env file is configured with correct database credentials"
echo "  2. Set DocumentRoot to: $PROJECT_ROOT/public"
echo "  3. Test the website in your browser"
echo "  4. Run: php deployment/fix-deployment-structure.php (to verify setup)"
echo ""
echo "See DEPLOYMENT_CHECKLIST.md for complete deployment guide"
echo ""
