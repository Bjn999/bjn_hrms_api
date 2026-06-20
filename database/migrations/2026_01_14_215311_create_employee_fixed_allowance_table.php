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
        Schema::create('employee_fixed_allowance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->references('id')->on('employees')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('allowance_id')->references('id')->on('allowances')->onUpdate('cascade');
            $table->decimal('value', 10, 2)->comment('قيمة البدل الثابت');
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
        Schema::dropIfExists('employee_fixed_allowance');
    }
};
