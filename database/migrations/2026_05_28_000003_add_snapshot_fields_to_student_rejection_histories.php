<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('student_rejection_histories', function (Blueprint $table) {
            $table->string('name')->nullable()->after('student_id');
            $table->string('email')->nullable()->after('name');
            $table->string('nis')->nullable()->after('email');
            $table->string('sekolah')->nullable()->after('nis');
            $table->string('kelas')->nullable()->after('sekolah');
            $table->text('alamat')->nullable()->after('kelas');
            $table->string('no_hp')->nullable()->after('alamat');
            $table->unsignedBigInteger('user_id')->nullable()->after('no_hp');
            $table->index('email');
        });
    }

    public function down(): void {
        Schema::table('student_rejection_histories', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn(['name','email','nis','sekolah','kelas','alamat','no_hp','user_id']);
        });
    }
};
