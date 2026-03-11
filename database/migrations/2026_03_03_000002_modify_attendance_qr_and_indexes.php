<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendance', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance', 'qr_id')) {
                $table->string('qr_id')->unique()->after('id');
            }
        });
        try {
            \DB::statement('ALTER TABLE attendance DROP INDEX attendance_student_id_tanggal_unique');
        } catch (\Exception $e) {}
    }

    public function down(): void {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('qr_id');
            $table->dropIndex(['student_id', 'tanggal']);
            $table->unique(['student_id', 'tanggal']);
        });
    }
};
