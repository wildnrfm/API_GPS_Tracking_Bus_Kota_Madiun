<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nik', 20)->unique();
            $table->string('no_hp', 20);
            $table->text('alamat');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('drivers');
    }
};
