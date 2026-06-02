<?php

namespace App\Console\Commands;

use App\Models\GpsTrack;
use Illuminate\Console\Command;

class CleanupGpsTracks extends Command
{
    protected $signature   = 'gps:cleanup';
    protected $description = 'Delete GPS tracks older than 30 days';

    public function handle()
    {
        $this->info('Starting GPS cleanup...');

        $deleted = 0;

        GpsTrack::where('recorded_at', '<', now()->subDays(30))
            ->chunkById(1000, function ($tracks) use (&$deleted) {
                foreach ($tracks as $track) {
                    $track->delete();
                    $deleted++;
                }
            });

        $this->info("Cleanup completed. Deleted {$deleted} records.");

        return Command::SUCCESS;
    }
}