<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_reports', 'bus_driver')) {
                $table->renameColumn('bus_driver', 'bus_driver_id');
            }
            if (Schema::hasColumn('daily_reports', 'jumlah_penumpang')) {
                $table->dropColumn('jumlah_penumpang');
            }
            if (!Schema::hasColumn('daily_reports', 'catatan_driver')) {
                $table->text('catatan_driver')->nullable()->after('bus_driver_id');
            }
        });
    }

    public function down(): void{
        Schema::table('daily_reports', function (Blueprint $table) {});
    }
};
