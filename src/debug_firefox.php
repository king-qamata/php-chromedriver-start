<?php
// debug_firefox.php
echo "=== Debugging Firefox/Geckodriver ===\n\n";

echo "1. Checking Firefox installation:\n";
$firefoxPath = shell_exec("which firefox");
if ($firefoxPath) {
    echo "   ✓ Firefox found: " . trim($firefoxPath) . "\n";
    $version = shell_exec("firefox --version 2>&1");
    echo "   Version: " . trim($version) . "\n";
} else {
    echo "   ✗ Firefox not found\n";
}

echo "\n2. Checking Geckodriver installation:\n";
$geckoPath = shell_exec("which geckodriver");
if ($geckoPath) {
    echo "   ✓ Geckodriver found: " . trim($geckoPath) . "\n";
    $version = shell_exec("geckodriver --version 2>&1");
    echo "   Version: " . trim($version) . "\n";
} else {
    echo "   ✗ Geckodriver not found\n";
}

echo "\n3. Checking Geckodriver service:\n";
$ch = curl_init('http://localhost:4444/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ Geckodriver is running on port 4444\n";
    $data = json_decode($response, true);
    echo "   Status: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "   ✗ Geckodriver is not running (HTTP: $httpCode)\n";
}

echo "\n4. Checking Firefox running as user:\n";
$user = shell_exec("whoami");
echo "   Current user: " . trim($user) . "\n";

echo "   Home directory: " . getenv('HOME') . "\n";
echo "   Home directory owner: ";
$owner = shell_exec("stat -c '%U:%G' " . escapeshellarg(getenv('HOME')) . " 2>/dev/null");
echo trim($owner ?: "Unknown") . "\n";

echo "\n5. Testing Firefox directly:\n";
$testCmd = "timeout 5 firefox --headless --screenshot /tmp/firefox-test.png https://example.com 2>&1";
$output = shell_exec($testCmd);
echo "   Command: $testCmd\n";
if (file_exists('/tmp/firefox-test.png')) {
    echo "   ✓ Firefox screenshot created: " . filesize('/tmp/firefox-test.png') . " bytes\n";
    unlink('/tmp/firefox-test.png');
} else {
    echo "   ✗ Firefox screenshot failed\n";
    if ($output) {
        echo "   Output: " . $output . "\n";
    }
}

echo "\n=== Debug Complete ===\n";