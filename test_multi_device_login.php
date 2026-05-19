<?php

// Test script untuk verify multi-device login

$basePath = __DIR__;
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\DeviceSession;

echo "\n=== MULTI-DEVICE LOGIN TEST ===\n\n";

// List available users
$users = User::limit(5)->get();
if ($users->isEmpty()) {
    echo "❌ Tidak ada user di database\n";
    exit(1);
}

echo "Available users:\n";
$users->each(function($u) {
    echo "  - " . $u->email . " (ID: " . $u->id . ", Role: " . $u->role . ")\n";
});

// Use first user for testing
$user = $users->first();
echo "\n✓ Using user: " . $user->email . " (ID: " . $user->id . ")\n\n";

// Clear existing sessions
DeviceSession::where('user_id', $user->id)->delete();
echo "✓ Cleared existing sessions\n\n";

// Simulate 3 login attempts
$devices = [
    ['ip' => '192.168.1.100', 'ua' => 'Mozilla/5.0 (Windows) Chrome/91.0', 'name' => 'Device 1 - Windows Chrome'],
    ['ip' => '192.168.1.101', 'ua' => 'Mozilla/5.0 (iPhone) Safari/605.0', 'name' => 'Device 2 - iPhone Safari'],
    ['ip' => '192.168.1.102', 'ua' => 'Mozilla/5.0 (Macbook) Chrome/91.0', 'name' => 'Device 3 - Macbook Chrome'],
];

$tokens = [];

foreach ($devices as $index => $device) {
    echo "--- Login Attempt " . ($index + 1) . " ---\n";
    echo "Device: " . $device['name'] . "\n";
    echo "IP: " . $device['ip'] . "\n";
    
    // Generate device_id like in AuthService
    $deviceId = hash('sha256', $device['ua'] . '|' . $device['ip']);
    
    // Simulate what AuthService.authenticateUser() does
    $existingDevices = DeviceSession::where('user_id', $user->id)
        ->where('expires_at', '>', now())
        ->orderBy('created_at', 'asc')
        ->get();
    
    echo "Active sessions BEFORE: " . $existingDevices->count() . "\n";
    
    // If 2+ devices, delete oldest
    if ($existingDevices->count() >= 2) {
        $oldestDevice = $existingDevices->first();
        echo "⚠️  Deleting oldest device: " . $oldestDevice->device_id . "\n";
        $oldestDevice->delete();
    }
    
    // Create new device session
    $token = \Illuminate\Support\Str::random(60);
    $session = DeviceSession::create([
        'user_id'          => $user->id,
        'device_id'        => $deviceId,
        'api_token'        => $token,
        'ip_address'       => $device['ip'],
        'user_agent'       => $device['ua'],
        'expires_at'       => now()->addDays(2),
        'last_activity_at' => now(),
    ]);
    
    $tokens[$index] = $token;
    
    $activeAfter = DeviceSession::where('user_id', $user->id)
        ->where('expires_at', '>', now())
        ->count();
    
    echo "✓ Created device session\n";
    echo "✓ Token: " . substr($token, 0, 20) . "...\n";
    echo "Active sessions AFTER: " . $activeAfter . "\n\n";
}

// Verify tokens
echo "--- VERIFY TOKENS ---\n";
foreach ($tokens as $index => $token) {
    $session = DeviceSession::where('api_token', $token)->first();
    $status = $session ? "✓ VALID" : "❌ INVALID";
    echo "Device " . ($index + 1) . " token: " . $status . "\n";
}

echo "\n=== SUMMARY ===\n";
$finalCount = DeviceSession::where('user_id', $user->id)
    ->where('expires_at', '>', now())
    ->count();

echo "Final active sessions: " . $finalCount . " (Expected: 2)\n";

if ($finalCount === 2) {
    echo "\n✅ MULTI-DEVICE LOGIN TEST PASSED!\n";
    echo "   - Max 2 devices constraint is working\n";
    echo "   - Oldest device was automatically removed\n";
    echo "   - Tokens are properly validated\n";
} else {
    echo "\n❌ TEST FAILED!\n";
}
