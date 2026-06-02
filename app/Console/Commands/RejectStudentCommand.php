<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StudentService;

class RejectStudentCommand extends Command
{
    protected $signature   = 'student:reject {id} {--reason="Ditolak oleh admin via CLI"}';
    protected $description = 'Reject student by id using StudentService::rejectStudent';

    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        parent::__construct();
        $this->studentService = $studentService;
    }

    public function handle()
    {
        $id     = $this->argument('id');
        $reason = $this->option('reason');

        $this->info("Rejecting student id: $id");

        $result = $this->studentService->rejectStudent($id, $reason, null);

        if (!$result['success']) {
            $this->error('Failed: ' . ($result['error'] ?? 'unknown'));
            return 1;
        }

        $this->info('Success: student rejected.');
        $this->line('Snapshot: ' . json_encode($result['history'] ?? $result));

        return 0;
    }
}