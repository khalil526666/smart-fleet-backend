<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('cin_number', 20)->nullable()->after('photo_path');
            $table->string('cin_photo_recto', 255)->nullable()->after('cin_number');
            $table->string('cin_photo_verso', 255)->nullable()->after('cin_photo_recto');
            $table->date('date_naissance')->nullable()->after('cin_photo_verso');
            $table->text('adresse')->nullable()->after('date_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'cin_number', 'cin_photo_recto', 'cin_photo_verso', 'date_naissance', 'adresse']);
        });
    }
};
