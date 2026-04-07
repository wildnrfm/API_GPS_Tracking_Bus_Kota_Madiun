<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('gps_tracks', function (Blueprint $table) {
            $table->float('accuracy')->nullable()->after('speed');   // meter
            $table->float('heading')->nullable()->after('accuracy'); // derajat 0-360
            $table->timestamp('device_timestamp')->nullable()->after('heading'); // timestamp dari device
        });
    }

    public function down(): void {
        Schema::table('gps_tracks', function (Blueprint $table) {
            $table->dropColumn(['accuracy', 'heading', 'device_timestamp']);
        });
    }
};