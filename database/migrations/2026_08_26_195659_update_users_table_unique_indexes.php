<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop existing unique indexes
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_username_unique');
            
            // Add composite unique indexes with company_id
            $table->unique(['email', 'company_id'], 'users_email_company_id_unique');
            $table->unique(['username', 'company_id'], 'users_username_company_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_company_id_unique');
            $table->dropUnique('users_username_company_id_unique');
            
            $table->unique('email', 'users_email_unique');
            $table->unique('username', 'users_username_unique');
        });
    }
};
