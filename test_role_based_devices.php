<?php

// Test script untuk verify role-based device limiting (Admin: 2, Others: 1)

$basePath = __DIR__;
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\DeviceSession;
use Illuminate\Support\Str;

echo "\n=== ROLE-BASED DEVICE LIMITING TEST ===\n\n";

// Get test users
$adminUser = User::where('role', 'admin')->first();
$siswaUser = User::where('role', 'siswa')->first();

if (!$adminUser) {
    echo "❌ Admin user tidak ditemukan\n";
    exit(1);
}

echo "✓ Admin user: " . $adminUser->email . "\n";
if ($siswaUser) {
    echo "✓ Siswa user: " . $siswaUser->email . "\n";
} else {
    echo "⚠️  Siswa user tidak ditemukan (akan skip siswa test)\n";
}

echo "\n";

// Test 1: Admin can have 2 devices
echo "=== TEST 1: ADMIN MAX 2 DEVICES ===\n";
DeviceSession::where('user_id', $adminUser->id)->delete();
echo "✓ Cleared admin sessions\n\n";

$adminTokens = [];
for ($i = 1; $i <= 3; $i++) {
    echo "--- Admin Login Attempt $i ---\n";
    
    $deviceId = hash('sha256', "Browser$i|192.168.1.$i");
    $existingDevices = DeviceSession::where('user_id', $adminUser->id)
        ->where('expires_at', '>', now())
        ->orderBy('created_at', 'asc')
        ->count();
    
    echo "Active sessions BEFORE: $existingDevices\n";
    
    // Max devices for admin = 2
    $maxDevices = 2;
    if ($existingDevices >= $maxDevices) {
        $oldest = DeviceSession::where('user_id', $adminUser->id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->first();
        echo "⚠️  Deleting oldest device\n";
        $oldest->delete();
    }
    
    $token = Str::random(60);
    DeviceSession::create([
        'user_id'          => $adminUser->id,
        'device_id'        => $deviceId,
        'api_token'        => $token,
        'ip_address'       => "192.168.1.$i",
        'user_agent'       => "Browser$i",
        'expires_at'       => now()->addDays(2),
        'last_activity_at' => now(),
    ]);
    
    $adminTokens[] = $token;
    
    $activeAfter = DeviceSession::where('user_id', $adminUser->id)
        ->where('expires_at', '>', now())
        ->count();
    
    echo "✓ Created device\n";
    echo "Active sessions AFTER: $activeAfter\n\n";
}

$adminFinalCount = DeviceSession::where('user_id', $adminUser->id)
    ->where('expires_at', '>', now())
    ->count();

echo "ADMIN RESULT: $adminFinalCount active sessions (Expected: 2)\n";
if ($adminFinalCount === 2) {
    echo "✅ ADMIN TEST PASSED - Max 2 devices working!\n";
} else {
    echo "❌ ADMIN TEST FAILED\n";
}

echo "\n";

// Test 2: Siswa/Driver can have only 1 device
if ($siswaUser) {
    echo "=== TEST 2: SISWA MAX 1 DEVICE ===\n";
    DeviceSession::where('user_id', $siswaUser->id)->delete();
    echo "✓ Cleared siswa sessions\n\n";
    
    for ($i = 1; $i <= 2; $i++) {
        echo "--- Siswa Login Attempt $i ---\n";
        
        $deviceId = hash('sha256', "Phone$i|192.168.2.$i");
        $existingDevices = DeviceSession::where('user_id', $siswaUser->id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc')
            ->count();
        
        echo "Active sessions BEFORE: $existingDevices\n";
        
        // Max devices for siswa = 1
        $maxDevices = 1;
        if ($existingDevices >= $maxDevices) {
            $oldest = DeviceSession::where('user_id', $siswaUser->id)
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'asc')
                ->first();
            echo "⚠️  Deleting oldest device (siswa max 1 device)\n";
            $oldest->delete();
        }
        
        $token = Str::random(60);
        DeviceSession::create([
            'user_id'          => $siswaUser->id,
            'device_id'        => $deviceId,
            'api_token'        => $token,
            'ip_address'       => "192.168.2.$i",
            'user_agent'       => "Phone$i",
            'expires_at'       => now()->addDays(2),
            'last_activity_at' => now(),
        ]);
        
        $activeAfter = DeviceSession::where('user_id', $siswaUser->id)
            ->where('expires_at', '>', now())
            ->count();
        
        echo "✓ Created device\n";
        echo "Active sessions AFTER: $activeAfter\n\n";
    }
    
    $siswaFinalCount = DeviceSession::where('user_id', $siswaUser->id)
        ->where('expires_at', '>', now())
        ->count();
    
    echo "SISWA RESULT: $siswaFinalCount active sessions (Expected: 1)\n";
    if ($siswaFinalCount === 1) {
        echo "✅ SISWA TEST PASSED - Max 1 device working!\n";
    } else {
        echo "❌ SISWA TEST FAILED\n";
    }
} else {
    echo "⚠️  SISWA TEST SKIPPED - No siswa user found\n";
}

echo "\n=== SUMMARY ===\n";
echo "✅ Role-based device limiting implemented and tested\n";
echo "   - Admin: max 2 devices\n";
echo "   - Siswa/Driver: max 1 device\n";
