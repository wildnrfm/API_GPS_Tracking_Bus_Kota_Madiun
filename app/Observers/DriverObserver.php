<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\BusDriver;

class DriverObserver {
    public function deleting(Driver $driver): void {
        BusDriver::where('driver_id', $driver->id)->delete();
    }

    public function restoring(Driver $driver): void {
        BusDriver::where('driver_id', $driver->id)->restore();
    }
}
