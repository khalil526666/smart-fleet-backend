<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('role');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('vehicle_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};

