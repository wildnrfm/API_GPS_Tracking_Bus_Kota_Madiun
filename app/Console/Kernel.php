<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Tandai missed checkouts — setiap tengah malam
        $schedule->command('attendance:mark-missed-checkouts')
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Missed checkouts marked successfully');
            })
            ->onFailure(function () {
                \Log::error('Failed to mark missed checkouts');
            });

        // Hapus GPS tracks lama — setiap jam 01:00
        $schedule->command('gps:cleanup')
            ->dailyAt('01:00')
            ->withoutOverlapping();

        // Hapus penugasan driver expired — setiap jam 02:00
        $schedule->command('bus-driver:delete-expired')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Bus driver expired assignments cleanup completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Bus driver expired assignments cleanup failed');
            });

        // Backup database — setiap jam 03:00
        $schedule->command('backup:database')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('Database backup completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Database backup failed');
            });

        // Proses offline queue — setiap 5 menit
        $schedule->job(new \App\Jobs\ProcessOfflineQueueJob())
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}