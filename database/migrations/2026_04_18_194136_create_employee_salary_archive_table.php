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
        Schema::create('employee_salary_archive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->references('id')->on('employees')->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('value', 10, 2)->comment('قيمة الراتب قبل التغيير');
            $table->foreignId('added_by')->references('id')->on('admins')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->references('id')->on('admins')->onUpdate('cascade');
            $table->integer('com_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_archive');
    }
};
