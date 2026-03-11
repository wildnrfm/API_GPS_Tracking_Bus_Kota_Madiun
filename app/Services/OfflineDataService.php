<?php

namespace App\Services;

use App\Models\GpsTrack;
use Illuminate\Support\Facades\DB;

class OfflineDataService {
    public static function queueRequest($endpoint, $payload, $method = 'POST', $deviceId = null){
        try {
            DB::table('offline_queue')->insert([
                'endpoint' => $endpoint,
                'method' => $method,
                'payload' => json_encode($payload),
                'device_id' => $deviceId,
                'created_at' => now(),
            ]);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to queue offline data: ' . $e->getMessage());
            return false;
        }
    }

    public static function processPendingRequests(){
        $pending = DB::table('offline_queue')->whereNull('sent_at')->where('retry_count', '<', 5)->orderBy('created_at')->limit(50)->get();
        foreach ($pending as $request) {
            self::retryRequest($request);
        }
        return $pending->count();
    }

    private static function retryRequest($queueItem){
        try {
            $payload = json_decode($queueItem->payload, true);
            if (strpos($queueItem->endpoint, 'gps-tracks') !== false) {
                GpsTrack::create($payload);
                DB::table('offline_queue')->where('id', $queueItem->id)->update([
                        'sent_at' => now(),
                        'last_attempted_at' => now(),
                    ]);
                return true;
            }
        } catch (\Exception $e) {
            DB::table('offline_queue')->where('id', $queueItem->id)->update([
                    'retry_count' => $queueItem->retry_count + 1,
                    'last_attempted_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
            return false;
        }
    }

    public static function logDataSync($entityType, $entityId, $deviceId, $localData, $status = 'pending'){
        try {
            DB::table('data_sync_logs')->insert([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'device_id' => $deviceId,
                'local_data' => json_encode($localData),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to log data sync: ' . $e->getMessage());
            return false;
        }
    }

    public static function markAsSynced($entityType, $entityId, $deviceId, $serverData = null){
        try {
            DB::table('data_sync_logs')->where('entity_type', $entityType)->where('entity_id', $entityId)->where('device_id', $deviceId)->whereIn('status', ['pending', 'failed'])->update([
                    'status' => 'synced',
                    'server_data' => $serverData ? json_encode($serverData) : null,
                    'updated_at' => now(),
                ]);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to mark as synced: ' . $e->getMessage());
            return false;
        }
    }

    public static function getPendingSyncs($deviceId){
        return DB::table('data_sync_logs')->where('device_id', $deviceId)->where('status', 'pending')->orderBy('created_at')->get();
    }

    public static function logGpsHealth($userId = null, $deviceId = null, $isEnabled = true, $hasSignal = true, $signalStrength = null){
        try {
            DB::table('gps_health_checks')->insert([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'is_gps_enabled' => $isEnabled,
                'has_signal' => $hasSignal,
                'signal_strength' => $signalStrength,
                'connection_status' => $this->getConnectionStatus(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to log GPS health: ' . $e->getMessage());
            return false;
        }
    }

    private static function getConnectionStatus(){
        return 'online';
    }

    public static function getLastGpsHealth($userId){
        return DB::table('gps_health_checks')->where('user_id', $userId)->orderBy('created_at', 'desc')->first();
    }

    public static function cleanupOldOfflineQueue(){
        return DB::table('offline_queue')->whereNotNull('sent_at')->where('created_at', '<', now()->subDays(30))->delete();
    }

    public static function cleanupOldSyncLogs() {
        return DB::table('data_sync_logs')->where('created_at', '<', now()->subDays(90))->delete();
    }
}
