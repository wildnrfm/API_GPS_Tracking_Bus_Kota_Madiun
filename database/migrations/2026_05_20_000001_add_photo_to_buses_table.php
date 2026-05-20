<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('buses', function (Blueprint $table) {
            // Check if column doesn't already exist before adding
            if (!Schema::hasColumn('buses', 'photo')) {
                $table->string('photo')->nullable()->after('status');
            }
        });
    }

    public function down(): void {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
