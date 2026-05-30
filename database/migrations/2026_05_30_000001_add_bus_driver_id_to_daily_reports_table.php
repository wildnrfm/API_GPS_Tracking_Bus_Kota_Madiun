<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_reports', 'bus_driver_id')) {
                $table->foreignId('bus_driver_id')
                    ->nullable()
                    ->after('bus_id')
                    ->constrained('bus_driver')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_reports', 'bus_driver_id')) {
                $table->dropForeign(['bus_driver_id']);
                $table->dropColumn('bus_driver_id');
            }
        });
    }
};
