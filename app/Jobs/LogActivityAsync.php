<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogActivityAsync implements ShouldQueue{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $action;
    protected $userId;
    protected $data;

    public function __construct($action, $userId, $data = []){
        $this->action = $action;
        $this->userId = $userId;
        $this->data = $data;
    }

    public function handle(): void{
        ActivityLog::log($this->action, $this->userId, $this->data);
    }
}
