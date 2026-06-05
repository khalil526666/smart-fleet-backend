<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MySQL: broaden enum, migrate values, then narrow to final set
            DB::statement("ALTER TABLE users MODIFY role ENUM('administrator','fleet_manager','user','gestionnaire','admin') NOT NULL DEFAULT 'user'");
            DB::table('users')->where('role', 'administrator')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'fleet_manager')->update(['role' => 'gestionnaire']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','gestionnaire','admin') NOT NULL DEFAULT 'user'");
        } elseif ($driver === 'sqlite') {
            // SQLite: CHECK constraints cannot be altered — rebuild the table without one.
            // This preserves all data while mapping old role values to the new canonical ones.
            DB::statement('PRAGMA foreign_keys=OFF');
            DB::statement('CREATE TABLE "users_tmp" (
                "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
                "name" varchar NOT NULL,
                "email" varchar NOT NULL,
                "password" varchar NOT NULL,
                "role" varchar NOT NULL DEFAULT \'user\',
                "photo_path" varchar,
                "created_at" datetime,
                "updated_at" datetime
            )');
            DB::statement("INSERT INTO \"users_tmp\"
                SELECT \"id\", \"name\", \"email\", \"password\",
                    CASE \"role\"
                        WHEN 'administrator' THEN 'admin'
                        WHEN 'fleet_manager' THEN 'gestionnaire'
                        ELSE 'user'
                    END,
                    \"photo_path\", \"created_at\", \"updated_at\"
                FROM \"users\"");
            DB::statement('DROP TABLE "users"');
            DB::statement('ALTER TABLE "users_tmp" RENAME TO "users"');
            DB::statement('CREATE UNIQUE INDEX "users_email_unique" ON "users"("email")');
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            DB::table('users')->where('role', 'administrator')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'fleet_manager')->update(['role' => 'gestionnaire']);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY role ENUM('administrator','fleet_manager') NOT NULL DEFAULT 'fleet_manager'");
        }

        DB::table('users')->where('role', 'admin')->update(['role' => 'administrator']);
        DB::table('users')->where('role', 'gestionnaire')->update(['role' => 'fleet_manager']);
        DB::table('users')->where('role', 'user')->update(['role' => 'fleet_manager']);
    }
};

