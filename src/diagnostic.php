<?php
// new_diagnostic.php - Tests the on-demand ChromeDriver approach

echo "<pre>";
echo "=== Testing On-Demand ChromeDriver Approach ===\n\n";

require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

// 1. Check basic installations
echo "1. Basic System Checks:\n";

// 1.i Check Chrome
echo "1.i Google Chrome:\n";
$chromePath = shell_exec("which google-chrome");
if ($chromePath) {
    echo "   ✓ Found: " . trim($chromePath) . "\n";
    $chromeVersion = shell_exec("google-chrome --version");
    echo "   Version: " . ($chromeVersion ? trim($chromeVersion) : "Unknown") . "\n";
} else {
    echo "   ❌ NOT FOUND\n";
}

// 1.ii Check ChromeDriver
echo "\n1.ii ChromeDriver:\n";
$driverPath = shell_exec("which chromedriver");
if ($driverPath) {
    echo "   ✓ Found: " . trim($driverPath) . "\n";
    $driverVersion = shell_exec("chromedriver --version");
    echo "   Version: " . ($driverVersion ? trim($driverVersion) : "Unknown") . "\n";
} else {
    echo "   ❌ NOT FOUND\n";
}


// 2. Check if persistent ChromeDriver is running (should NOT be)
echo "\n2. Checking for Persistent ChromeDriver (should be NOT running):\n";
$processes = shell_exec("ps aux | grep '[c]hromedriver.*--port=9515'");
if ($processes) {
    echo "   ⚠ WARNING: Persistent ChromeDriver is running on port 9515\n";
    echo "   This will conflict with ChromeDriver::start()\n";
    echo "   Process info:\n";
    echo $processes . "\n";
} else {
    echo "   ✓ No persistent ChromeDriver on port 9515 (good!)\n";
}

// 3. Check Xvfb
echo "\n3. Checking Xvfb:\n";
$xvfbProcess = shell_exec("ps aux | grep '[X]vfb'");
if ($xvfbProcess) {
    echo "   ✓ Xvfb is running\n";
} else {
    echo "   ❌ Xvfb is NOT running (required for Chrome)\n";
}

// 4. Try to start ChromeDriver on-demand
echo "\n4. Testing ChromeDriver::start():\n";

try {
    // Set environment variables
    putenv('HOME=/tmp/www-data');
    putenv('DISPLAY=:99');
    
    // Create unique profile
    $userDataDir = '/tmp/chrome-profiles/test-profile-' . uniqid();
    mkdir($userDataDir, 0755, true);
    
    // Create Chrome options
    $options = new ChromeOptions();
    $options->addArguments([
        '--headless=new',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--user-data-dir=' . $userDataDir,
        '--window-size=1920,1080'
    ]);
    
    // Create capabilities
    $capabilities = DesiredCapabilities::chrome();
    $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
    
    echo "   Starting ChromeDriver...\n";
    
    // Try different methods
    $method = 1;
    $driver = null;
    
    try {
        // Method 1: Direct start
        echo "   Trying method 1: ChromeDriver::start()...\n";
        $driver = ChromeDriver::start($capabilities);
        $method = 1;
    } catch (Exception $e1) {
        echo "   Method 1 failed: " . $e1->getMessage() . "\n";
        
        try {
            // Method 2: Start with explicit port 0 (random)
            echo "   Trying method 2: ChromeDriver::start() with port 0...\n";
            $driver = ChromeDriver::start($capabilities, null, ['port' => 0]);
            $method = 2;
        } catch (Exception $e2) {
            echo "   Method 2 failed: " . $e2->getMessage() . "\n";
            
            try {
                // Method 3: Alternative approach
                echo "   Trying method 3: Alternative approach...\n";
                $driver = ChromeDriver::startFromChromeDriverCommand(null, [], $capabilities);
                $method = 3;
            } catch (Exception $e3) {
                echo "   Method 3 failed: " . $e3->getMessage() . "\n";
                throw new Exception("All ChromeDriver start methods failed");
            }
        }
    }
    
    echo "   ✓ ChromeDriver started successfully (method $method)\n";
    
    // Get the server address
    $serverUrl = $driver->getCommandExecutor()->getAddressOfRemoteServer();
    echo "   Server URL: $serverUrl\n";
    
    // Test a simple navigation
    echo "   Testing navigation...\n";
    $driver->get('http://example.com');
    $title = $driver->getTitle();
    echo "   Page title: $title\n";
    
    // Check if we got a valid response
    if (strpos($title, 'Example') !== false || strlen($title) > 0) {
        echo "   ✓ Navigation successful\n";
    } else {
        echo "   ⚠ Navigation may have failed (unexpected title)\n";
    }
    
    // Clean up
    echo "   Cleaning up...\n";
    $driver->quit();
    echo "   ✓ Driver stopped\n";
    
    // Check for processes after cleanup
    sleep(1);
    $remaining = shell_exec("ps aux | grep -E '[c]hrome|[c]hromedriver' | wc -l");
    $remaining = intval(trim($remaining));
    echo "   Remaining Chrome/ChromeDriver processes: $remaining\n";
    
    if ($remaining > 0) {
        echo "   ⚠ Warning: $remaining process(es) still running after cleanup\n";
    } else {
        echo "   ✓ All processes cleaned up properly\n";
    }
    
    echo "\n   ✓✓✓ ChromeDriver::start() test PASSED ✓✓✓\n";
    
} catch (Exception $e) {
    echo "\n   ❌❌❌ TEST FAILED ❌❌❌\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    
    // Check what's running
    echo "\n   Current processes:\n";
    echo shell_exec("ps aux | grep -E '[c]hrome|[c]hromedriver'") ?: "   None found\n";
}

// 5. Clean up test directory
if (isset($userDataDir) && is_dir($userDataDir)) {
    shell_exec("rm -rf " . escapeshellarg($userDataDir));
}

echo "\n=== Diagnostic Complete ===\n";
echo "</pre>";