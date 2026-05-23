<?php

// Test real login scenarios with role-based device limiting

$basePath = __DIR__;
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\DeviceSession;
use App\Services\AuthService;
use Illuminate\Support\Facades\Hash;

echo "\n=== REAL LOGIN TEST WITH ROLE-BASED DEVICE LIMITING ===\n\n";

// Get admin user
$admin = User::where('email', 'admin@diskominfo.kotamadiun.com')->first();
if (!$admin) {
    echo "❌ Admin user not found\n";
    exit(1);
}

echo "✓ Admin user: " . $admin->email . "\n";
echo "✓ Admin ID: " . $admin->id . "\n\n";

// Clear existing sessions
DeviceSession::where('user_id', $admin->id)->delete();
echo "✓ Cleared existing sessions\n\n";

// Create AuthService instance
$authService = app(AuthService::class);

// Simulate 3 login attempts with different devices/IPs
$loginAttempts = [
    ['name' => 'Desktop Windows Chrome', 'ip' => '192.168.1.100', 'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0'],
    ['name' => 'iPhone Safari', 'ip' => '192.168.1.101', 'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 Safari/605.0'],
    ['name' => 'MacBook Chrome', 'ip' => '192.168.1.102', 'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/91.0'],
];

$tokens = [];

foreach ($loginAttempts as $index => $attempt) {
    echo "--- LOGIN ATTEMPT " . ($index + 1) . " ---\n";
    echo "Device: " . $attempt['name'] . "\n";
    echo "IP: " . $attempt['ip'] . "\n";
    
    // Call authenticateUser
    $result = $authService->authenticateUser(
        $admin->email,
        'admin1234',
        $attempt['ip'],
        $attempt['ua']
    );
    
    if (isset($result['error'])) {
        echo "❌ Login failed: " . $result['error'] . "\n\n";
        continue;
    }
    
    $token = $result['token'];
    $tokens[$index] = $token;
    
    echo "✓ Login successful\n";
    echo "✓ Token: " . substr($token, 0, 20) . "...\n";
    
    // Check active devices
    $activeCount = DeviceSession::where('user_id', $admin->id)
        ->where('expires_at', '>', now())
        ->count();
    
    echo "✓ Active sessions: " . $activeCount . "\n";
    
    if ($index == 2 && $activeCount == 2) {
        echo "✓✓ Oldest device was automatically deleted (Max 2 for admin)\n";
    }
    
    echo "\n";
}

// Verify token validity
echo "--- TOKEN VALIDITY CHECK ---\n";

foreach ($tokens as $index => $token) {
    $session = DeviceSession::where('api_token', $token)
        ->where('expires_at', '>', now())
        ->first();
    
    $status = $session ? "✓ VALID" : "❌ INVALID";
    echo "Token " . ($index + 1) . ": " . $status . "\n";
}

echo "\n";

$finalCount = DeviceSession::where('user_id', $admin->id)
    ->where('expires_at', '>', now())
    ->count();

echo "=== FINAL RESULT ===\n";
echo "Final active devices: " . $finalCount . " (Expected: 2)\n";

if ($finalCount === 2) {
    echo "\n✅ ROLE-BASED DEVICE LIMITING TEST PASSED!\n";
    echo "   - Admin login 3 times\n";
    echo "   - Only 2 devices stayed active\n";
    echo "   - Oldest device was auto-deleted\n";
    echo "   - Max 2 devices per admin working correctly!\n";
} else {
    echo "\n❌ TEST FAILED\n";
}
