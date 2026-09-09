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
        Schema::create('vacation_types', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 255)->comment('اسم نوع الإجازة');
            $table->string('type_slug', 50)->default('annual')->comment('رمز النوع: annual, sick, casual, unpaid, hajj, maternity, etc.');
            $table->tinyInteger('is_paid')->default(1)->comment('1=مدفوعة الأجر، 0=غير مدفوعة الأجر (خصم راتب)');
            $table->tinyInteger('deduct_from_balance')->default(1)->comment('1=تخصم من رصيد الإجازات السنوية، 0=لا تخصم');
            $table->decimal('max_days_per_year', 8, 2)->default(0)->comment('الحد الأقصى للأيام في السنة (0=غير محدد/حسب الرصيد)');
            $table->tinyInteger('requires_attachment')->default(0)->comment('1=تتطلب إرفاق تقرير/ملف');
            $table->tinyInteger('active')->default(1)->comment('1=مفعلة، 0=معطلة');
            
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'comp_created_vac_type_idx');
            $table->index(['company_id', 'active'], 'comp_active_vac_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_types');
    }
};
