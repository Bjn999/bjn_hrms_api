<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'initial_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('initial_password', 100)->nullable()->after('password');
            });
        }

        // Generate a unique 8-digit random initial password for existing regular employee accounts
        $employeeUsers = DB::table('users')
            ->whereNotNull('employee_id')
            ->whereNotIn('role', ['super_admin', 'company_admin'])
            ->whereNull('initial_password')
            ->get(['id']);

        foreach ($employeeUsers as $empUser) {
            $randomPass = (string) random_int(10000000, 99999999);
            DB::table('users')
                ->where('id', $empUser->id)
                ->update([
                    'password' => Hash::make($randomPass),
                    'initial_password' => $randomPass,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'initial_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('initial_password');
            });
        }
    }
};

