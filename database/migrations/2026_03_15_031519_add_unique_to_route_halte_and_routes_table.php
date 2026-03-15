<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_halte', function (Blueprint $table) {
            $table->unique(['route_id', 'halte_id'], 'route_halte_unique');
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->unique('bus_id', 'routes_bus_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('route_halte', function (Blueprint $table) {
            $table->dropUnique('route_halte_unique');
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropUnique('routes_bus_id_unique');
        });
    }
};