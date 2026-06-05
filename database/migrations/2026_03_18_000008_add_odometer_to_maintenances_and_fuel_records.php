<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->unsignedInteger('odometer')->nullable()->after('date');
        });

        Schema::table('fuel_records', function (Blueprint $table) {
            $table->unsignedInteger('odometer')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn('odometer');
        });

        Schema::table('fuel_records', function (Blueprint $table) {
            $table->dropColumn('odometer');
        });
    }
};

