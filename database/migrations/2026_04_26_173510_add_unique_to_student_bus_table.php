<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Hapus duplikat dulu sebelum tambah constraint
        // Simpan hanya 1 record per student_id (yang paling baru)
        DB::statement('
            DELETE sb1 FROM student_bus sb1
            INNER JOIN student_bus sb2
            WHERE sb1.id < sb2.id
            AND sb1.student_id = sb2.student_id
        ');

        Schema::table('student_bus', function (Blueprint $table) {
            // Satu siswa hanya boleh di-assign ke 1 bus
            $table->unique('student_id', 'student_bus_student_id_unique');
        });
    }

    public function down(): void {
        Schema::table('student_bus', function (Blueprint $table) {
            $table->dropUnique('student_bus_student_id_unique');
        });
    }
};