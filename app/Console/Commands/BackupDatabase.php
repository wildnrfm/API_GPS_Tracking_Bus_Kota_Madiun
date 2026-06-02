<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--full} {--compress}';
    protected $description = 'Backup database secara berkala (harian/mingguan)';

    public function handle()
    {
        $this->info('Starting database backup...');

        try {
            $database = config('database.connections.mysql.database');
            $user     = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host     = config('database.connections.mysql.host');

            // Pastikan direktori backup tersedia
            $backupDir = storage_path('backups');
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Buat nama file dengan timestamp
            $filename = "backup_" . Carbon::now()->format('Y-m-d_H-i-s') . ".sql";
            $filepath = $backupDir . '/' . $filename;

            // Susun perintah mysqldump
            $command = "mysqldump --user={$user} --password={$password} --host={$host} {$database}";

            if ($this->option('compress')) {
                $command  .= " | gzip";
                $filename .= ".gz";
                $filepath .= ".gz";
            }

            $command .= " > " . escapeshellarg($filepath);

            $output     = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->error('Backup gagal: ' . implode("\n", $output));
                return 1;
            }

            $fileSize         = filesize($filepath);
            $fileSizeReadable = $this->formatBytes($fileSize);

            $this->info("✓ Backup berhasil: {$filename} ({$fileSizeReadable})");

            $this->cleanupOldBackups($backupDir);

            // Catat backup sukses ke database
            DB::table('backup_logs')->insert([
                'filename'    => $filename,
                'filepath'    => $filepath,
                'file_size'   => $fileSize,
                'backup_type' => $this->option('full') ? 'full' : 'regular',
                'compressed'  => $this->option('compress') ? 1 : 0,
                'status'      => 'success',
                'created_at'  => now(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Error during backup: ' . $e->getMessage());

            // Catat backup gagal ke database
            DB::table('backup_logs')->insert([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'created_at'    => now(),
            ]);

            return 1;
        }
    }

    // Hapus backup lama, pertahankan 10 file terbaru
    private function cleanupOldBackups($backupDir)
    {
        $files = array_slice(
            scandir($backupDir, SCANDIR_SORT_DESCENDING),
            10
        );

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && strpos($file, 'backup_') === 0) {
                $filepath = $backupDir . '/' . $file;
                if (is_file($filepath)) {
                    unlink($filepath);
                    $this->info("Cleaned up old backup: {$file}");
                }
            }
        }
    }

    // Konversi bytes ke format yang mudah dibaca
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}