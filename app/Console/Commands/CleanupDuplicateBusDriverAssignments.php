<?php

namespace App\Console\Commands;

use App\Services\BusService;
use Illuminate\Console\Command;

class CleanupDuplicateBusDriverAssignments extends Command
{
    protected $signature   = 'bus-driver:cleanup-duplicates {--dry-run}';
    protected $description = 'Detect and cleanup duplicate active bus-driver assignments safely';

    protected BusService $busService;

    public function __construct(BusService $busService)
    {
        parent::__construct();
        $this->busService = $busService;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $result = $this->busService->cleanupDuplicateActiveAssignments($dryRun);

        if (!$result['success']) {
            $this->error('Failed to cleanup duplicate assignments');
            return 1;
        }

        $this->info('Duplicate active assignment groups:');
        $this->line('  Drivers with duplicates: ' . $result['duplicate_driver_groups']);
        $this->line('  Buses with duplicates:   ' . $result['duplicate_bus_groups']);

        if ($dryRun) {
            $this->info('Dry-run mode: no records changed.');
            return 0;
        }

        $this->info('Cleanup completed successfully.');
        $this->line('  Updated rows: ' . $result['updated_rows']);

        return 0;
    }
}