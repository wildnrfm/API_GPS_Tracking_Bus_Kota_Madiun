<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('offline_queue', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('method')->default('POST');
            $table->json('payload');
            $table->string('device_id')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->index('sent_at');
            $table->index('created_at');
            $table->index('retry_count');
        });
    }

    public function down(): void {
        Schema::dropIfExists('offline_queue');
    }
};
