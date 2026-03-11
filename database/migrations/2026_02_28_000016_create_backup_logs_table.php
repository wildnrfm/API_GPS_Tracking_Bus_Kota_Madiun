<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->string('filepath')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('backup_type')->default('regular');
            $table->boolean('compressed')->default(false);
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void {
        Schema::dropIfExists('backup_logs');
    }
};
