<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('route_polylines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('urutan');      // urutan titik dalam polyline (1, 2, 3, ...)
            $table->timestamps();

            $table->index(['route_id', 'urutan']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('route_polylines');
    }
};
