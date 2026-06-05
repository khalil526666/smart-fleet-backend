<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','gestionnaire','chauffeur','admin') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        // Existing 'chauffeur' values would become invalid; map back to 'user' before narrowing.
        DB::table('users')->where('role', 'chauffeur')->update(['role' => 'user']);
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','gestionnaire','admin') NOT NULL DEFAULT 'user'");
        }
    }
};

