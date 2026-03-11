<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;

class MarkMissedCheckouts extends Command {
    protected $signature = 'attendance:mark-missed-checkouts';
    protected $description = 'Mark attendance as NOT_CHECKED_OUT if student forgot to check out (runs at midnight)';

    public function handle() {
        $yesterday = now()->subDay()->toDateString();
        $updated = Attendance::where('tanggal', $yesterday)->whereNull('waktu_turun')->update([
                'status' => 'not_checked_out',
                'waktu_turun' => '-',
                'lat_turun' => '-',
                'lng_turun' => '-',
            ]);
        $this->info("Marked {$updated} attendance records as NOT_CHECKED_OUT");
        return Command::SUCCESS;
    }
}
