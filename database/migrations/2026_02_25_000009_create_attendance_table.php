<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->foreignId('halte_naik_id')->constrained('haltes')->cascadeOnDelete();
            $table->date('tanggal');
            $table->timestamp('waktu_naik')->nullable();
            $table->timestamp('waktu_turun')->nullable();
            $table->decimal('lat_naik', 10, 7)->nullable();
            $table->decimal('lng_naik', 10, 7)->nullable();
            $table->decimal('lat_turun', 10, 7)->nullable();
            $table->decimal('lng_turun', 10, 7)->nullable();
            $table->enum('status', ['checked_in', 'checked_out', 'not_checked_out'])->default('checked_in');
            $table->timestamp('qr_expires_at')->nullable();
            $table->timestamps();

            // index
            $table->index(['student_id','tanggal']);
            $table->index(['bus_id','tanggal']);
            $table->unique(['student_id','tanggal']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('attendance');
    }
};
