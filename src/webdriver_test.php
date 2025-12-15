<?php
require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

// Set environment variables
putenv('HOME=/tmp/www-data');
putenv('XDG_CONFIG_HOME=/tmp/www-data/.config');
putenv('XDG_CACHE_HOME=/tmp/www-data/.cache');
putenv('XDG_DATA_HOME=/tmp/www-data/.local/share');
putenv('DISPLAY=:99');

class ChromeDriverManager
{
    private $driver = null;
    
    public function startBrowser()
    {
        try {
            echo "=== Starting new ChromeDriver instance ===\n";
            
            // Create unique profile directory for this session
            $userDataDir = '/tmp/chrome-profiles/profile-' . uniqid();
            if (!is_dir($userDataDir)) {
                mkdir($userDataDir, 0755, true);
                echo "Created profile directory: $userDataDir\n";
            }
            
            // Create Chrome options
            $options = new ChromeOptions();
            $options->addArguments([
                '--headless=new',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--remote-debugging-port=0',
                '--user-data-dir=' . $userDataDir,
                '--disable-blink-features=AutomationControlled',
                '--no-first-run',
                '--disable-extensions',
                '--window-size=1920,1080'
            ]);
            
            // Optional: Add experimental options
            $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
            $options->setExperimentalOption('useAutomationExtension', false);
            
            // Create capabilities
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
            
            // Add additional Chrome capabilities
            $capabilities->setCapability('acceptInsecureCerts', true);
            
            echo "Starting ChromeDriver service...\n";
            
            // ChromeDriver::start() returns the driver instance directly
            $this->driver = ChromeDriver::start($capabilities, null, ['port' => 0]);
            
            // Get the URL (includes the port)
            $url = $this->driver->getCommandExecutor()->getAddressOfRemoteServer();
            echo "✓ ChromeDriver started successfully at: $url\n";
            
            return $this->driver;
            
        } catch (Exception $e) {
            echo "❌ Failed to start ChromeDriver: " . $e->getMessage() . "\n";
            $this->cleanup();
            throw $e;
        }
    }
    
    public function getDriver()
    {
        return $this->driver;
    }
    
    public function cleanup()
    {
        echo "Cleaning up resources...\n";
        
        // Quit driver first
        if ($this->driver instanceof ChromeDriver) {
            try {
                $this->driver->quit();
                echo "✓ Browser closed\n";
            } catch (Exception $e) {
                echo "⚠ Could not close browser cleanly: " . $e->getMessage() . "\n";
            }
            $this->driver = null;
        }
        
        // Clean up profile directories older than 1 hour
        $this->cleanupOldProfiles();
    }
    
    private function cleanupOldProfiles()
    {
        $profiles = glob('/tmp/chrome-profiles/profile-*');
        foreach ($profiles as $profile) {
            if (is_dir($profile) && (time() - filemtime($profile) > 3600)) {
                shell_exec("rm -rf " . escapeshellarg($profile) . " 2>/dev/null");
            }
        }
    }
    
    public function __destruct()
    {
        $this->cleanup();
    }
}

// Main execution
echo "=== Testing ChromeDriver::start() ===\n";

$manager = null;
$driver = null;

try {
    // Create manager instance
    $manager = new ChromeDriverManager();
    
    // Start browser
    $driver = $manager->startBrowser();
    
    if (!$driver) {
        throw new Exception("Failed to create driver instance");
    }
    
    // Test navigation
    echo "Navigating to example.com...\n";
    $driver->get('https://example.com');
    
    // Get page title
    $title = $driver->getTitle();
    echo "✓ Page title: " . $title . "\n";
    
    // Verify page loaded
    $currentUrl = $driver->getCurrentURL();
    echo "Current URL: " . $currentUrl . "\n";
    
    // Optional: Take screenshot
    // $screenshotPath = '/tmp/screenshot-' . uniqid() . '.png';
    // $driver->takeScreenshot($screenshotPath);
    // echo "Screenshot saved to: $screenshotPath\n";
    
    // Optional: Get page source
    // $pageSource = $driver->getPageSource();
    // echo "Page source length: " . strlen($pageSource) . " bytes\n";
    
    echo "✓ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
    
    // Show stack trace for debugging
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    
} finally {
    // Clean up
    if ($manager) {
        $manager->cleanup();
    }
    
    // Additional cleanup for any orphaned processes
    killOrphanedProcesses();
    
    echo "=== Test finished ===\n";
}

/**
 * Kill any orphaned Chrome/ChromeDriver processes
 */
function killOrphanedProcesses()
{
    echo "Checking for orphaned processes...\n";
    
    // Kill Chrome processes
    exec("pkill -f 'chrome.*--user-data-dir=/tmp/chrome-profiles/profile-' 2>/dev/null", $output, $chromeReturn);
    if ($chromeReturn === 0) {
        echo "✓ Cleaned up orphaned Chrome processes\n";
    }
    
    // Kill ChromeDriver processes
    exec("pkill -f 'chromedriver.*--port=' 2>/dev/null", $output, $driverReturn);
    if ($driverReturn === 0) {
        echo "✓ Cleaned up orphaned ChromeDriver processes\n";
    }
    
    // Check if any processes are still running
    exec("pgrep -f 'chrome.*--user-data-dir=' 2>/dev/null", $chromePids);
    exec("pgrep -f 'chromedriver.*--port=' 2>/dev/null", $driverPids);
    
    if (empty($chromePids) && empty($driverPids)) {
        echo "✓ No orphaned processes remain\n";
    } else {
        echo "⚠ Warning: Some processes may still be running\n";
        if (!empty($chromePids)) {
            echo "  Chrome PIDs: " . implode(', ', $chromePids) . "\n";
        }
        if (!empty($driverPids)) {
            echo "  ChromeDriver PIDs: " . implode(', ', $driverPids) . "\n";
        }
    }
}