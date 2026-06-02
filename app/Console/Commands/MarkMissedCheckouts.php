<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class MarkMissedCheckouts extends Command
{
    protected $signature   = 'attendance:mark-missed-checkouts';
    protected $description = 'Mark attendance as NOT_CHECKED_OUT if student forgot to check out (runs at midnight)';

    public function handle()
    {
        $yesterday = now()->subDay()->toDateString();

        // Mark checked_in tanpa checkout sebagai not_checked_out
        $updated = Attendance::where('tanggal', $yesterday)
            ->where('status', 'checked_in')
            ->whereNotNull('waktu_naik')
            ->whereNull('waktu_turun')
            ->update([
                'status'      => 'not_checked_out',
                'waktu_turun' => null,
                'lat_turun'   => null,
                'lng_turun'   => null,
            ]);

        // Hapus record pending yang tidak pernah dipakai (QR tidak discan)
        $deleted = Attendance::where('tanggal', $yesterday)
            ->where('status', 'pending')
            ->whereNull('waktu_naik')
            ->delete();

        $this->info("Marked {$updated} attendance as NOT_CHECKED_OUT, deleted {$deleted} unused pending QR records");

        return Command::SUCCESS;
    }
}