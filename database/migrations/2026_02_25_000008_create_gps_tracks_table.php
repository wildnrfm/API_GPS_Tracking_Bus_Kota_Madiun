<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('gps_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // index
            $table->index(['bus_id', 'recorded_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('gps_tracks');
    }
};
