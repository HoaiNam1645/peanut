<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Storage;

echo "========================================\n";
echo "Testing B2 Connection\n";
echo "========================================\n\n";

// Test 1: Check B2 config
echo "1. Checking B2 Configuration:\n";
echo "   - Key ID: " . env('B2_ACCESS_KEY_ID') . "\n";
echo "   - Region: " . env('B2_DEFAULT_REGION') . "\n";
echo "   - Bucket: " . env('B2_BUCKET') . "\n";
echo "   - Endpoint: " . env('B2_ENDPOINT') . "\n";
echo "   - Public URL: " . env('B2_URL_CLOUD') . "\n\n";

// Test 2: Upload test file
echo "2. Testing file upload to B2:\n";
try {
    $testContent = "Test file created at " . date('Y-m-d H:i:s');
    $testPath = "test/connection-test.txt";
    
    Storage::disk('b2')->put($testPath, $testContent);
    echo "   ✓ File uploaded successfully\n";
    
    // Get URL
    $url = Storage::disk('b2')->url($testPath);
    echo "   ✓ File URL: $url\n\n";
    
    // Test 3: Check if file exists
    echo "3. Verifying file exists:\n";
    if (Storage::disk('b2')->exists($testPath)) {
        echo "   ✓ File exists on B2\n";
    } else {
        echo "   ✗ File not found on B2\n";
    }
    
    // Test 4: Read file
    echo "\n4. Reading file content:\n";
    $content = Storage::disk('b2')->get($testPath);
    echo "   Content: $content\n";
    
    // Test 5: Delete test file
    echo "\n5. Cleaning up test file:\n";
    Storage::disk('b2')->delete($testPath);
    echo "   ✓ Test file deleted\n";
    
    echo "\n========================================\n";
    echo "✓ B2 Connection Test PASSED\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "\n========================================\n";
    echo "✗ B2 Connection Test FAILED\n";
    echo "========================================\n";
    exit(1);
}
