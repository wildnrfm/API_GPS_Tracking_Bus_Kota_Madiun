<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->date('tanggal');
            $table->integer('total_penumpang');
            $table->text('catatan_driver')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('daily_reports');
    }
};