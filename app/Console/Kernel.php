<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
    protected function schedule(Schedule $schedule): void {

        // melewatkan proses check-out - setiap hari tengah malam
        $schedule->command('attendance:mark-missed-checkouts')->dailyAt('00:00')->withoutOverlapping()->onSuccess(function () {
                \Log::info('Missed checkouts marked successfully');
            })->onFailure(function () {
                \Log::error('Failed to mark missed checkouts');
            });

        // setiap hari jam 1 pagi
        $schedule->command('gps:cleanup')->dailyAt('01:00')->withoutOverlapping();

        // setiap hari jam 2 pagi, Hapus otomatis penugasan driver yang expired (tanggal_selesai sudah lewat)
        $schedule->command('bus-driver:delete-expired')->dailyAt('02:00')->withoutOverlapping()->onSuccess(function () {
                \Log::info('Bus driver expired assignments cleanup completed successfully');
            })->onFailure(function () {
                \Log::error('Bus driver expired assignments cleanup failed');
            });

        // Database backup - setiap hari jam 3 pagi
        $schedule->command('backup:database')->dailyAt('03:00')->withoutOverlapping()->onSuccess(function () {
                \Log::info('Database backup completed successfully');
            })->onFailure(function () {
                \Log::error('Database backup failed');
            });

        // Process offline queue - setiap 5 menit
        $schedule->job(new \App\Jobs\ProcessOfflineQueueJob())->everyFiveMinutes()->withoutOverlapping();
    }

    protected function commands(): void {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
