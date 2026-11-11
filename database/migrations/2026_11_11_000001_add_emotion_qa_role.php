<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin', 'emotion_qa', 'hospital_admin', 'hospital_viewer') NOT NULL DEFAULT 'hospital_viewer'");
        DB::statement("ALTER TABLE user_invitations MODIFY role ENUM('super_admin', 'emotion_qa', 'hospital_admin', 'hospital_viewer') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin', 'hospital_admin', 'hospital_viewer') NOT NULL DEFAULT 'hospital_viewer'");
        DB::statement("ALTER TABLE user_invitations MODIFY role ENUM('super_admin', 'hospital_admin', 'hospital_viewer') NOT NULL");
    }
};
