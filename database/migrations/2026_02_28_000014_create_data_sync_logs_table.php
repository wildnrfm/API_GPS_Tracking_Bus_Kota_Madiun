<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('data_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('device_id');
            $table->string('status');
            $table->json('local_data');
            $table->json('server_data')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'device_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('data_sync_logs');
    }
};
