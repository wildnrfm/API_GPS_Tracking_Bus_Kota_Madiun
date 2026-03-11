<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('kode_bus', 20)->unique();
            $table->string('plat_nomor', 15)->unique();
            $table->enum('status', ['aktif', 'nonaktif', 'maintenance']);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('buses');
    }
};
