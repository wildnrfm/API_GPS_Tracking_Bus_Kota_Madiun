<?php

namespace App\Console\Commands;

use App\Models\BusDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteExpiredBusDriverAssignments extends Command {
    protected $signature = 'bus-driver:delete-expired';
    protected $description = 'Automatically delete expired bus-driver assignments where tanggal_selesai has passed';

    public function handle() {
        $today = now()->toDateString();

        // Soft delete assignments yang tanggal_selesainya sudah lewat
        $deleted = BusDriver::where('tanggal_selesai', '<', $today)->delete();
        $this->info("Deleted {$deleted} expired bus-driver assignments on {$today}");
        \Log::info("Bus-Driver Expired Assignments Cleanup", [
            'deleted_count' => $deleted,
            'run_date' => $today,
            'timestamp' => now()
        ]);
        return 0;
    }
}
