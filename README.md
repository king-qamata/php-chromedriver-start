# ChromeDriver Automation Setup

## Table of Contents
- [Overview](#overview)
- [Architecture](#architecture)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)
- [Monitoring](#monitoring)
- [Best Practices](#best-practices)
- [Migration Guide](#migration-guide)
- [FAQ](#faq)

## Overview

This project provides a scalable ChromeDriver automation setup for PHP applications running in Azure App Service (Linux). It uses an on-demand ChromeDriver instance approach where each script starts its own isolated ChromeDriver process, providing better resource management and avoiding port conflicts.

### Key Features
- 🚀 **On-demand ChromeDriver instances** - No persistent service conflicts
- 🛡️ **Isolated sessions** - Each script gets its own Chrome profile
- 🔧 **Automatic cleanup** - Resources freed after use
- 📊 **Comprehensive diagnostics** - Built-in monitoring tools
- ⚡ **Optimized for Azure** - Pre-configured for Azure App Service

## Architecture

### Old Architecture (Deprecated)
```
┌─────────────────────────────────┐
│  Persistent ChromeDriver Service│
│  Port: 9515                    │
│  Managed by Supervisor         │
└───────────────┬─────────────────┘
                │
    ┌───────────▼───────────┐
    │  Multiple PHP Scripts  │
    │  Shared Connection     │
    └───────────────────────┘
```
**Issues**: Port conflicts, shared resources, scalability limitations

### New Architecture (Current)
```
┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
│  PHP Script 1   │   │  PHP Script 2   │   │  PHP Script N   │
│  ┌───────────┐  │   │  ┌───────────┐  │   │  ┌───────────┐  │
│  │ChromeDriver│  │   │  │ChromeDriver│  │   │  │ChromeDriver│  │
│  │Port: Random│  │   │  │Port: Random│  │   │  │Port: Random│  │
│  └───────────┘  │   │  └───────────┘  │   │  └───────────┘  │
└─────────────────┘   └─────────────────┘   └─────────────────┘
        │                      │                      │
        └──────────────────────┼──────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │      Xvfb (:99)     │
                    │  Virtual Display    │
                    └─────────────────────┘
```
**Benefits**: No conflicts, isolated sessions, better scalability

## Prerequisites

### System Requirements
- **Azure App Service**: Linux (PHP 8.3)
- **Memory**: Minimum 1GB, Recommended 2GB+ for multiple instances
- **Storage**: 1GB free space in `/tmp`

### Software Dependencies
- Google Chrome (Latest stable)
- ChromeDriver (Matching Chrome version)
- Xvfb (X Virtual Framebuffer)
- PHP 8.3+ with Composer
- Facebook WebDriver PHP library

## Installation

### 1. Docker Build

```dockerfile
# Build the Docker image
docker build -t php-chromedriver-app .
```

### 2. Environment Setup

Create necessary directories:
```bash
mkdir -p /tmp/chrome-profiles
mkdir -p /tmp/www-data
chmod 777 /tmp/chrome-profiles
```

### 3. Composer Dependencies

```bash
composer require php-webdriver/webdriver
```

## Configuration

### Environment Variables

Add to your Azure Application Settings or `.env` file:

```bash
# Required
HOME=/tmp/www-data
DISPLAY=:99

# Optional (for debugging)
CHROME_LOG_LEVEL=1
CHROMEDRIVER_LOG_LEVEL=INFO
WEBDRIVER_CHROME_DRIVER=/usr/local/bin/chromedriver
```

### PHP Configuration (`php-azure.ini`)

```ini
; Chrome Automation Settings
memory_limit = 512M
max_execution_time = 180
max_input_time = 90

; Error Handling
display_errors = Off
log_errors = On
error_log = /home/LogFiles/php_errors.log
```

### Chrome Options Configuration

```php
$options = new ChromeOptions();
$options->addArguments([
    '--headless=new',           # Required: New headless mode
    '--no-sandbox',             # Required: Docker compatibility
    '--disable-dev-shm-usage',  # Required: Shared memory fix
    '--disable-gpu',            # Optional: Disable GPU acceleration
    '--window-size=1920,1080',  # Optional: Browser window size
    '--disable-blink-features=AutomationControlled', # Anti-detection
    '--no-first-run',           # Skip first run dialogs
    '--disable-extensions',     # Disable extensions for stability
]);
```

## Usage

### Basic Example

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

// Set environment
putenv('HOME=/tmp/www-data');
putenv('DISPLAY=:99');

try {
    // 1. Configure Chrome
    $options = new ChromeOptions();
    $options->addArguments([
        '--headless=new',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--window-size=1920,1080'
    ]);
    
    // 2. Create capabilities
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    
    // 3. Start ChromeDriver (on-demand)
    $driver = ChromeDriver::start($capabilities);
    
    // 4. Use the browser
    $driver->get('https://example.com');
    echo "Title: " . $driver->getTitle();
    
    // 5. Take screenshot
    $driver->takeScreenshot('/tmp/screenshot.png');
    
    // 6. Clean up
    $driver->quit();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Advanced: ChromeDriverManager Class

```php
<?php
class ChromeDriverManager {
    private $driver;
    
    public function startBrowser($options = []) {
        // Set environment
        putenv('HOME=/tmp/www-data');
        putenv('DISPLAY=:99');
        
        // Create Chrome options
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments(array_merge([
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
            '--user-data-dir=' . $this->createProfileDir()
        ], $options));
        
        // Start driver
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
        
        $this->driver = ChromeDriver::start($capabilities);
        return $this->driver;
    }
    
    public function cleanup() {
        if ($this->driver) {
            $this->driver->quit();
            $this->driver = null;
        }
    }
    
    private function createProfileDir() {
        $dir = '/tmp/chrome-profiles/profile-' . uniqid();
        mkdir($dir, 0755, true);
        return $dir;
    }
}
```

### Running Multiple Instances

```php
<?php
// Example: Running 3 concurrent browsers
$managers = [];
$drivers = [];

for ($i = 0; $i < 3; $i++) {
    $manager = new ChromeDriverManager();
    $driver = $manager->startBrowser();
    
    $drivers[] = $driver;
    $managers[] = $manager;
    
    // Navigate each to different URLs
    $driver->get("https://example-$i.com");
    echo "Browser $i: " . $driver->getTitle() . "\n";
}

// Clean up all
foreach ($managers as $manager) {
    $manager->cleanup();
}
```

## API Reference

### ChromeDriverManager Methods

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `startBrowser()` | `$options` (array) | `ChromeDriver` | Starts a new Chrome instance |
| `cleanup()` | None | void | Cleans up browser resources |
| `takeScreenshot()` | `$path` (string) | bool | Takes screenshot of current page |
| `executeJavaScript()` | `$script` (string) | mixed | Executes JavaScript in browser |

### Common Chrome Options

| Option | Description | Required |
|--------|-------------|----------|
| `--headless=new` | Headless mode | Yes |
| `--no-sandbox` | Disable sandbox (Docker) | Yes |
| `--disable-dev-shm-usage` | Fix shared memory issues | Yes |
| `--window-size=WIDTH,HEIGHT` | Browser window size | No |
| `--user-data-dir=PATH` | Custom profile directory | No |
| `--disable-blink-features=AutomationControlled` | Anti-detection | No |

## Troubleshooting

### Common Issues

#### 1. ChromeDriver Fails to Start
```bash
# Check Chrome installation
google-chrome --version

# Check ChromeDriver installation
chromedriver --version

# Check Xvfb
ps aux | grep Xvfb
```

#### 2. Memory Issues
```php
// Increase PHP memory limit
ini_set('memory_limit', '1024M');

// Reduce Chrome memory usage
$options->addArguments([
    '--disable-images',
    '--disable-javascript',
    '--single-process'
]);
```

#### 3. Timeout Errors
```php
// Increase timeout
$driver->manage()->timeouts()->pageLoadTimeout(60);
$driver->manage()->timeouts()->implicitlyWait(30);
```

#### 4. Profile Directory Errors
```bash
# Fix permissions
chmod 777 /tmp/chrome-profiles
chown www-data:www-data /tmp/www-data

# Clean old profiles
find /tmp/chrome-profiles -name "profile-*" -type d -mmin +30 -exec rm -rf {} \;
```

### Diagnostic Scripts

Run these scripts to diagnose issues:

1. **Basic Diagnostic** (`diagnostic.php`)
```bash
# Shows system status
php diagnostic.php
```

2. **ChromeDriver Test** (`simple_test.php`)
```bash
# Tests basic ChromeDriver functionality
php simple_test.php
```

3. **Advanced Diagnostic** (`new_diagnostic.php`)
```bash
# Tests on-demand ChromeDriver approach
php new_diagnostic.php
```

### Error Codes and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| `ERR_CONNECTION_REFUSED` | ChromeDriver not running | Check Xvfb, restart service |
| `ERR_TIMED_OUT` | Network/Chrome issues | Increase timeouts, check memory |
| `ERR_PROFILE_NOT_FOUND` | Permission issues | Fix /tmp permissions |
| `ERR_CHROME_NOT_FOUND` | Chrome not installed | Reinstall Chrome |

## Monitoring

### Log Files

| Log File | Location | Purpose |
|----------|----------|---------|
| Xvfb Log | `/home/LogFiles/xvfb.log` | Virtual display server |
| Chrome Log | `/home/LogFiles/chrome.log` | Chrome browser logs |
| PHP Error Log | `/home/LogFiles/php_errors.log` | PHP application errors |
| Nginx Access Log | `/home/LogFiles/nginx/access.log` | Web server access |

### Performance Metrics

Monitor these key metrics:

1. **Memory Usage**
```bash
# Check Chrome processes
ps aux | grep chrome | awk '{sum += $4} END {print sum "% memory"}'

# Check total memory
free -h
```

2. **Process Count**
```bash
# Count ChromeDriver processes
ps aux | grep chromedriver | grep -v grep | wc -l

# Count Chrome processes
ps aux | grep chrome | grep -v grep | wc -l
```

3. **Disk Usage**
```bash
# Check profile directory size
du -sh /tmp/chrome-profiles

# Check available disk space
df -h /tmp
```

### Health Check Endpoint

Create a health check endpoint:

```php
<?php
// health.php
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check Chrome
exec('which google-chrome', $output, $returnCode);
$health['checks']['chrome'] = $returnCode === 0 ? 'healthy' : 'unhealthy';

// Check ChromeDriver
exec('which chromedriver', $output, $returnCode);
$health['checks']['chromedriver'] = $returnCode === 0 ? 'healthy' : 'unhealthy';

// Check Xvfb
exec('ps aux | grep Xvfb | grep -v grep', $output, $returnCode);
$health['checks']['xvfb'] = count($output) > 0 ? 'healthy' : 'unhealthy';

// Check directory permissions
$health['checks']['permissions'] = is_writable('/tmp/chrome-profiles') ? 'healthy' : 'unhealthy';

echo json_encode($health, JSON_PRETTY_PRINT);
```

## Best Practices

### 1. Resource Management
```php
// Always use try-catch-finally
try {
    $driver = ChromeDriver::start($capabilities);
    // ... your code ...
} catch (Exception $e) {
    // Handle error
} finally {
    // Always cleanup
    if (isset($driver)) {
        $driver->quit();
    }
}
```

### 2. Efficient Chrome Options
```php
// Optimize for scraping
$options->addArguments([
    '--headless=new',
    '--disable-images',           // Save bandwidth
    '--disable-notifications',    // Reduce popups
    '--disable-popup-blocking',   // Control popups
    '--disable-background-timer-throttling', // Better performance
    '--disable-renderer-backgrounding',      // Prevent background throttling
]);
```

### 3. Session Management
```php
// Reuse driver within same script
$driver = ChromeDriver::start($capabilities);

// Multiple operations
$driver->get('https://site1.com');
// ... process ...
$driver->get('https://site2.com');
// ... process ...

// Clean up once
$driver->quit();
```

### 4. Error Handling
```php
try {
    $driver->get($url);
} catch (WebDriverException $e) {
    if (strpos($e->getMessage(), 'timeout') !== false) {
        // Handle timeout
        $driver->navigate()->refresh();
    } elseif (strpos($e->getMessage(), 'no such element') !== false) {
        // Handle missing element
        throw new ElementNotFoundException($e->getMessage());
    }
    throw $e;
}
```

## Migration Guide

### From Persistent to On-Demand

#### Old Code (Before Migration)
```php
// Old: Using persistent service
$host = 'http://localhost:9515';
$driver = RemoteWebDriver::create($host, $capabilities);
```

#### New Code (After Migration)
```php
// New: Using on-demand instances
$driver = ChromeDriver::start($capabilities);

// Don't forget to cleanup!
$driver->quit();
```

### Configuration Changes

| Old Setting | New Setting | Action Required |
|-------------|-------------|-----------------|
| Supervisor config | Removed | Delete `supervisord-chromedriver.conf` |
| Port 9515 | Random ports | No action - handled automatically |
| Shared profiles | Isolated profiles | No action - handled automatically |

### Testing Migration

1. **Run Diagnostic**
```bash
php new_diagnostic.php
```

2. **Test Basic Functionality**
```bash
php simple_test.php
```

3. **Monitor Resources**
```bash
# During migration, monitor processes
watch -n 1 'ps aux | grep -E "chrome|chromedriver" | grep -v grep'
```

## FAQ

### Q: Why switch from persistent to on-demand ChromeDriver?
**A**: Persistent ChromeDriver caused port conflicts and resource contention. On-demand instances provide better isolation and scalability.

### Q: How much memory does each Chrome instance use?
**A**: Approximately 300-500MB per instance. Monitor with `ps aux | grep chrome`.

### Q: Can I run multiple Chrome instances concurrently?
**A**: Yes, each script can start its own instance. Monitor system resources to avoid overloading.

### Q: How do I handle Chrome crashes?
**A**: Use try-catch blocks and implement retry logic. The system automatically cleans up orphaned processes.

### Q: Where are browser profiles stored?
**A**: In `/tmp/chrome-profiles/profile-{unique-id}/`. These are automatically cleaned up.

### Q: How do I update Chrome/ChromeDriver?
**A**: Rebuild the Docker image. Chrome and ChromeDriver versions must match.

### Q: Can I use this with other browsers?
**A**: This setup is specifically for Chrome/ChromeDriver. Other browsers require different configurations.

### Q: How do I debug headless Chrome?
**A**: Remove `--headless=new` temporarily or use `--remote-debugging-port=9222`.

## Support

### Getting Help

1. **Check Logs**
```bash
tail -f /home/LogFiles/xvfb.log
tail -f /home/LogFiles/php_errors.log
```

2. **Run Diagnostics**
```bash
php diagnostic.php
```

3. **Monitor Resources**
```bash
# Real-time monitoring
htop
```

### Common Solutions

| Problem | Quick Fix |
|---------|-----------|
| Chrome won't start | Check Xvfb, increase memory |
| Timeout errors | Increase PHP/Chrome timeouts |
| Permission errors | `chmod 777 /tmp/chrome-profiles` |
| Port conflicts | Kill all ChromeDriver processes |

### Emergency Procedures

1. **Stop All Chrome Processes**
```bash
pkill -9 chrome
pkill -9 chromedriver
```

2. **Clean Temporary Files**
```bash
rm -rf /tmp/chrome-profiles/*
```

3. **Restart Services**
```bash
# Restart Xvfb
pkill Xvfb
Xvfb :99 -screen 0 1920x1080x24 &
```

## Contributing

### Reporting Issues

When reporting issues, include:
1. PHP version
2. Chrome version
3. ChromeDriver version
4. Error logs
5. Diagnostic script output

### Feature Requests

Submit feature requests with:
1. Use case description
2. Expected behavior
3. Proposed implementation

---

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- [Facebook WebDriver](https://github.com/php-webdriver/php-webdriver) - PHP WebDriver client
- [Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/) - Chrome/ChromeDriver binaries
- [Azure App Service](https://azure.microsoft.com/en-us/services/app-service/) - Hosting platform

---

*Last Updated: December 2024*  
*Version: 2.0.0 (On-Demand Architecture)*