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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('اسم المستخدم');
            $table->string('email')->unique()->nullable()->comment('البريد الالكتروني');
            $table->string('username')->unique()->nullable()->comment('اسم الدخول');
            $table->string('password');
            $table->string('role')->default('employee')->comment('super_admin, company_admin, employee');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('معرف الموظف المرتبط بهذا الحساب');
            $table->foreignUuid('company_id')->nullable()->comment('معرف الشركة')->constrained('companies')->onUpdate('cascade')->cascadeOnDelete();
            $table->boolean('is_active')->default(1)->comment('حالة تفعيل الحساب');
            $table->rememberToken();
            $table->timestamps();
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'name'], 'comp_name_idx');
            $table->index(['company_id', 'email'], 'comp_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
