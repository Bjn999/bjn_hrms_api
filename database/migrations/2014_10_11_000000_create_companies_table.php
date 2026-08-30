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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name')->index()->comment('اسم الشركة');
            $table->string('company_code')->unique()->nullable()->comment('كود تعريفي اضافي للشركة');
            $table->string('email')->nullable()->comment('البريد الإلكتروني للشركة');
            $table->string('phones')->nullable()->comment('هواتف الشركة');
            $table->string('logo')->nullable()->comment('شعار الشركة');
            $table->string('address')->nullable()->comment('عنوان الشركة');
            $table->string('commercial_record')->nullable()->comment('السجل التجاري');
            $table->string('tax_number')->nullable()->comment('الرقم الضريبي');
            $table->boolean('is_active')->default(1)->comment('حالة تفعيل الشركة');
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
