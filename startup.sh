#!/bin/bash

# Set environment variables for Chrome
export HOME=/tmp/www-data
export XDG_CONFIG_HOME=/tmp/www-data/.config
export XDG_CACHE_HOME=/tmp/www-data/.cache
export XDG_DATA_HOME=/tmp/www-data/.local/share
export DISPLAY=:99

# Create necessary directories
mkdir -p /tmp/www-data/.config
mkdir -p /tmp/www-data/.cache
mkdir -p /tmp/www-data/.local/share
mkdir -p /tmp/chrome-profiles
mkdir -p /home/LogFiles

chown -R www-data:www-data /tmp/www-data
chmod -R 755 /tmp/www-data
chmod -R 777 /tmp/chrome-profiles /home/LogFiles

# Clean up any existing processes
echo "Cleaning up existing processes..."
pkill -f Xvfb 2>/dev/null || true
pkill -f chrome 2>/dev/null || true
sleep 2

# Clean old profiles
find /tmp/chrome-profiles -name "profile-*" -type d -mmin +30 -exec rm -rf {} + 2>/dev/null || true

# Start Xvfb (still needed for Chrome)
echo "Starting Xvfb..."
nohup Xvfb :99 -screen 0 1920x1080x24 > /home/LogFiles/xvfb.log 2>&1 &
sleep 2

# Verify Xvfb is running
if pgrep -f "Xvfb" > /dev/null; then
    echo "✓ Xvfb is running"
else
    echo "❌ Xvfb is not running"
fi

# Start the Azure startup process (nginx and PHP-FPM)
echo "Starting Azure services..."
#exec /opt/startup/startup.sh
service nginx reload