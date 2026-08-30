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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('emp_name')->comment('الاسم الأول');
            $table->string('middle_name', 100)->nullable()->after('first_name')->comment('الاسم الأوسط');
            $table->string('last_name', 100)->nullable()->after('middle_name')->comment('الاسم الأخير');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
