# ChromeDriver Setup Documentation

## Overview

This setup has been restructured from a persistent ChromeDriver service to an on-demand ChromeDriver instance approach. Instead of running a single ChromeDriver service on port 9515, each PHP script now starts its own ChromeDriver instance when needed.

## Key Changes

### Before (Old Setup):
- ✅ Persistent ChromeDriver service running on port 9515
- ✅ Managed by Supervisor
- ✅ Single shared instance
- ❌ Port conflicts when using ChromeDriver::start()
- ❌ Limited scalability

### After (New Setup):
- ✅ On-demand ChromeDriver instances
- ✅ No port conflicts
- ✅ Better isolation between sessions
- ✅ Each script gets its own ChromeDriver
- ✅ Automatic cleanup of resources

## Files Updated

### 1. **Dockerfile**
- Removed Supervisor ChromeDriver configuration
- Simplified ChromeDriver installation
- No persistent service setup

### 2. **startup.sh** (CRITICAL - Must Update)
- Removed the persistent ChromeDriver service startup
- Only starts Xvfb (required for Chrome)
- Cleanup of old ChromeDriver processes
- **IMPORTANT**: Make sure to replace your existing startup.sh with the new version

### 3. **Removed Files**
- `supervisord-chromedriver.conf` - No longer needed

## Directory Structure

```
/home/site/wwwroot/
├── vendor/                    # Composer dependencies
├── src/                      # Application source code
├── webdriver_test.php        # Main test script
├── diagnostic.php           # Diagnostic tool
├── new_diagnostic.php       # New approach diagnostic
├── simple_test.php          # Minimal working example
└── webdriver_test_fixed.php # Fixed version

/tmp/
├── chrome-profiles/         # Chrome user profiles (auto-cleaned)
│   └── profile-{unique-id}/ # Individual session profiles
└── www-data/               # Home directory for www-data user

/home/LogFiles/             # Log directory
```

## PHP Scripts

### 1. **Main Test Script** (`webdriver_test_fixed.php`)
The primary script using the new on-demand approach.

**Features:**
- Starts ChromeDriver on-demand
- Creates unique profile directory
- Proper error handling
- Automatic cleanup
- Screenshot capability

**Usage:**
```php
// Minimal working example
$driver = ChromeDriver::start($capabilities);
$driver->get('https://example.com');
echo $driver->getTitle();
$driver->quit();
```

### 2. **Diagnostic Tools**

**`diagnostic.php`** - Comprehensive system check:
- Chrome/ChromeDriver installation
- Running processes
- Network ports
- HTTP endpoints
- Log files

**`new_diagnostic.php`** - Tests the new on-demand approach:
- System readiness check
- Multiple startup method testing
- Process cleanup verification

### 3. **Simple Test** (`simple_test.php`)
Minimal working example for quick testing.

## Environment Variables

Required environment variables (set in startup.sh):

```bash
export HOME=/tmp/www-data
export DISPLAY=:99
export XDG_CONFIG_HOME=/tmp/www-data/.config
export XDG_CACHE_HOME=/tmp/www-data/.cache
export XDG_DATA_HOME=/tmp/www-data/.local/share
```

## Chrome Options Configuration

Standard Chrome arguments used:

```php
$options->addArguments([
    '--headless=new',           # New headless mode
    '--no-sandbox',             # Required for Docker
    '--disable-dev-shm-usage',  # Fixes limited resource problems
    '--disable-gpu',            # GPU acceleration in Docker
    '--window-size=1920,1080',  # Standard window size
    '--user-data-dir=...',      # Unique profile per session
]);
```

## Resource Management

### Automatic Cleanup
- Profiles older than 30 minutes are automatically removed
- Orphaned Chrome/ChromeDriver processes are killed on startup
- Each script cleans up its own resources after execution

### Manual Cleanup Commands
```bash
# Kill all Chrome processes
pkill -f 'chrome.*--user-data-dir='

# Kill all ChromeDriver processes
pkill -f 'chromedriver.*--port='

# Clean old profiles
find /tmp/chrome-profiles -name "profile-*" -type d -mmin +30 -exec rm -rf {} \;
```

## Troubleshooting

### Common Issues and Solutions:

1. **"ChromeDriver failed to start"**
   - Check if Chrome is installed: `google-chrome --version`
   - Check if Xvfb is running: `ps aux | grep Xvfb`
   - Verify permissions: `ls -la /tmp/chrome-profiles/`

2. **"Port already in use"**
   - Ensure no persistent ChromeDriver is running: `pkill -f chromedriver`
   - Check ports: `netstat -tuln | grep :9515`

3. **"Cannot create profile directory"**
   - Check permissions: `chmod 777 /tmp/chrome-profiles`
   - Check disk space: `df -h /tmp`

4. **"Chrome crashes immediately"**
   - Check logs: `tail -f /home/LogFiles/xvfb.log`
   - Increase memory limits in php-azure.ini

### Diagnostic Commands:
```bash
# Check Chrome installation
which google-chrome
google-chrome --version

# Check ChromeDriver installation
which chromedriver
chromedriver --version

# Check running processes
ps aux | grep -E '(chrome|chromedriver|Xvfb)'

# Check network ports
netstat -tuln | grep :9515

# Check log files
tail -f /home/LogFiles/xvfb.log
```

## Performance Considerations

### Memory Usage
- Each ChromeDriver instance uses ~100-200MB RAM
- Each Chrome browser instance uses ~300-500MB RAM
- Profiles are stored in `/tmp` (tmpfs for better performance)

### Best Practices:
1. **Always call `quit()`** - Ensures proper cleanup
2. **Reuse driver instances** within the same script when possible
3. **Limit concurrent instances** based on available memory
4. **Use headless mode** to reduce resource usage
5. **Clean profiles regularly** to prevent disk space issues

## Security Notes

1. **Profile Isolation**: Each session gets its own profile directory
2. **Temporary Storage**: Profiles are stored in `/tmp` (volatile)
3. **Process Isolation**: Each ChromeDriver runs in its own process
4. **No Persistent Data**: All session data is cleaned up after use

## Monitoring

### Key Metrics to Monitor:
- Number of active ChromeDriver processes
- Memory usage of Chrome processes
- Disk usage in `/tmp/chrome-profiles`
- Successful vs failed ChromeDriver startups

### Log Locations:
- `/home/LogFiles/xvfb.log` - Xvfb virtual display server
- PHP error logs - Application errors
- Nginx logs - Web server access/errors

## Deployment Checklist

- [ ] Update `startup.sh` with new version
- [ ] Remove `supervisord-chromedriver.conf`
- [ ] Test with `simple_test.php`
- [ ] Verify diagnostic tools work
- [ ] Update any existing PHP code to use new approach
- [ ] Monitor resource usage initially
- [ ] Set up cleanup cron job if needed

## Support

For issues:
1. Run `diagnostic.php` to check system status
2. Check log files in `/home/LogFiles/`
3. Verify Chrome and ChromeDriver versions match
4. Ensure Xvfb is running (`DISPLAY=:99`)

## Migration Notes

### Code Changes Required:
1. Replace `RemoteWebDriver::create()` with `ChromeDriver::start()`
2. Remove explicit port configurations
3. Add proper cleanup with `quit()`
4. Update Chrome options for headless mode

### Before (Old Code):
```php
$service = new ChromeDriverService(9515);
$driver = RemoteWebDriver::create('http://localhost:9515', $capabilities);
```

### After (New Code):
```php
$driver = ChromeDriver::start($capabilities);
// ... use driver ...
$driver->quit();
```

This restructured setup provides better scalability, avoids port conflicts, and offers cleaner resource management for web automation tasks in the Azure PHP environment.