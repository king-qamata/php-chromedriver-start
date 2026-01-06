<?php
// firefox_test.php
require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Firefox\FirefoxProfile;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

echo "=== Testing Firefox/Geckodriver ===\n";

// Check if Geckodriver is running
$ch = curl_init('http://localhost:4444/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Geckodriver is not running on port 4444. HTTP Code: $httpCode\n");
}

echo "✓ Geckodriver is running on port 4444\n";

// Create Firefox options
$options = new FirefoxOptions();

// Add arguments to run Firefox in headless mode and bypass restrictions
$options->addArguments([
    '-headless',
    '-no-sandbox',
    '-disable-gpu'
]);

// Create a Firefox profile
$profile = new FirefoxProfile();
$profile->setPreference('browser.download.folderList', 2);
$profile->setPreference('browser.download.manager.showWhenStarting', false);
$profile->setPreference('browser.download.dir', '/tmp');
$profile->setPreference('browser.helperApps.neverAsk.saveToDisk', 'text/plain');

$options->setProfile($profile);

$capabilities = DesiredCapabilities::firefox();
$capabilities->setCapability(FirefoxOptions::CAPABILITY, $options);

$driver = null;

try {
    echo "Connecting to Geckodriver...\n";
    
    $driver = RemoteWebDriver::create('http://localhost:4444', $capabilities, 30000, 30000);
    
    echo "✓ Successfully connected to Geckodriver\n";
    
    echo "Navigating to example.com...\n";
    $driver->get('https://example.com');
    
    $title = $driver->getTitle();
    echo "✓ Page title: " . $title . "\n";
    
    echo "Closing browser...\n";
    $driver->quit();
    $driver = null;
    
    echo "✓ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
    
    // Try alternative approach if it fails
    if (strpos($e->getMessage(), 'root') !== false || strpos($e->getMessage(), 'HOME') !== false) {
        echo "\n⚠️  Firefox root permission issue detected. Trying alternative...\n";
        
        // Try without profile
        $simpleOptions = new FirefoxOptions();
        $simpleOptions->addArguments([
            '-headless',
            '-no-sandbox'
        ]);
        
        $simpleCapabilities = DesiredCapabilities::firefox();
        $simpleCapabilities->setCapability(FirefoxOptions::CAPABILITY, $simpleOptions);
        
        try {
            $driver2 = RemoteWebDriver::create('http://localhost:4444', $simpleCapabilities);
            $driver2->get('https://example.com');
            echo "✓ Alternative worked! Title: " . $driver2->getTitle() . "\n";
            $driver2->quit();
        } catch (Exception $e2) {
            echo "✗ Alternative also failed: " . $e2->getMessage() . "\n";
        }
    }
    
} finally {
    if ($driver instanceof RemoteWebDriver) {
        try {
            $driver->quit();
        } catch (Exception $e) {
            // Ignore
        }
    }
}