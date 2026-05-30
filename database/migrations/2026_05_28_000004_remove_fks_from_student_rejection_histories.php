<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('student_rejection_histories')) {
            return;
        }

        Schema::table('student_rejection_histories', function (Blueprint $table) {
            try {
                $table->dropForeign(['student_id']);
            } catch (\Exception $e) {
            }
            try {
                $table->dropForeign(['rejected_by']);
            } catch (\Exception $e) {
            }
        });
    }

    public function down(): void {
        if (!Schema::hasTable('student_rejection_histories')) {
            return;
        }

        Schema::table('student_rejection_histories', function (Blueprint $table) {
            try {
                $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            } catch (\Exception $e) {
            }
            try {
                $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            } catch (\Exception $e) {
            }
        });
    }
};
