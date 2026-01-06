#!/bin/bash

# Firefox specific setup
echo "=== Setting up Firefox/Geckodriver ==="

# Create Firefox profile directory
mkdir -p /tmp/firefox-profiles
chmod -R 777 /tmp/firefox-profiles
chown -R www-data:www-data /tmp/firefox-profiles 2>/dev/null || true

# Kill any existing geckodriver processes
pkill -f geckodriver 2>/dev/null || true
sleep 2

# Start Geckodriver as www-data user (not root)
echo "Starting Geckodriver as www-data user..."
su -s /bin/sh -c "/usr/local/bin/geckodriver --port=4444 --log trace > /home/LogFiles/geckodriver.log 2>&1 &" www-data

# Wait for Geckodriver to start
echo "Waiting for Geckodriver to start..."
for i in {1..10}; do
    if pgrep -f "geckodriver" > /dev/null; then
        echo "Geckodriver process is running"
        
        if netstat -tuln 2>/dev/null | grep -q ":4444" || ss -tuln 2>/dev/null | grep -q ":4444"; then
            echo "Port 4444 is listening"
            
            if curl -s http://localhost:4444/status > /dev/null; then
                echo "✓ Geckodriver started successfully on port 4444"
                break
            fi
        fi
    fi
    
    if [ $i -eq 10 ]; then
        echo "❌ Geckodriver failed to start after 10 attempts"
        if [ -f /home/LogFiles/geckodriver.log ]; then
            echo "Last 10 lines of Geckodriver log:"
            tail -10 /home/LogFiles/geckodriver.log
        fi
    else
        echo "Attempt $i/10: Waiting for geckodriver..."
        sleep 3
    fi
done

echo "=== Firefox setup completed ==="


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