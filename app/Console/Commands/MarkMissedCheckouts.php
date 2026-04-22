<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class MarkMissedCheckouts extends Command {
    protected $signature = 'attendance:mark-missed-checkouts';
    protected $description = 'Mark attendance as NOT_CHECKED_OUT if student forgot to check out (runs at midnight)';

    public function handle() {
        $yesterday = now()->subDay()->toDateString();

        // Hanya mark record yang sudah check-in (status checked_in, waktu_naik ada)
        // Skip record 'pending' (QR dibuat tapi tidak dipakai) — hapus saja
        $updated = Attendance::where('tanggal', $yesterday)
            ->where('status', 'checked_in')
            ->whereNotNull('waktu_naik')
            ->whereNull('waktu_turun')
            ->update([
                'status'     => 'not_checked_out',
                // Jangan set string '-' pada kolom timestamp/decimal — gunakan null
                'waktu_turun' => null,
                'lat_turun'   => null,
                'lng_turun'   => null,
            ]);

        // Hapus record pending kemarin yang tidak terpakai (QR tidak pernah discan)
        $deleted = Attendance::where('tanggal', $yesterday)
            ->where('status', 'pending')
            ->whereNull('waktu_naik')
            ->delete();

        $this->info("Marked {$updated} attendance as NOT_CHECKED_OUT, deleted {$deleted} unused pending QR records");
        return Command::SUCCESS;
    }
}