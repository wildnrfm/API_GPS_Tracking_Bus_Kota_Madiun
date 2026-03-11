<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('gps_health_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('device_id')->nullable();
            $table->boolean('is_gps_enabled');
            $table->boolean('has_signal');
            $table->integer('signal_strength')->nullable();
            $table->string('connection_status');
            $table->timestamps();
            $table->index('user_id');
            $table->index('device_id');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('gps_health_checks');
    }
};
