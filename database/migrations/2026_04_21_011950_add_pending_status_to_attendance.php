<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Ubah enum status agar mendukung nilai 'pending'
        // (dibutuhkan sejak myBarcode() menyimpan record sebelum driver scan)
        DB::statement("ALTER TABLE attendance MODIFY COLUMN status ENUM('pending','checked_in','checked_out','not_checked_out') NOT NULL DEFAULT 'checked_in'");

        // qr_id harus nullable karena kode lama mungkin tidak selalu menyertakan qr_id
        // dan untuk backward-compatibility dengan data lama
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('qr_id')->nullable()->change();
        });
    }

    public function down(): void {
        // Hapus semua record pending sebelum rollback enum (agar tidak ada data yang invalid)
        DB::table('attendance')->where('status', 'pending')->delete();
        DB::statement("ALTER TABLE attendance MODIFY COLUMN status ENUM('checked_in','checked_out','not_checked_out') NOT NULL DEFAULT 'checked_in'");

        Schema::table('attendance', function (Blueprint $table) {
            $table->string('qr_id')->nullable(false)->change();
        });
    }
};