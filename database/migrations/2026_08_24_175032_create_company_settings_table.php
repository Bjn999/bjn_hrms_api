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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Attendance Policies
            $table->decimal('after_miniute_calculate_delay', 10, 2)->default(0)->comment('بعد كم دقيقة يحسب تأخير');
            $table->decimal('after_miniute_calculate_early_departure', 10, 2)->default(0)->comment('بعد كم دقيقة يحسب انصراف مبكر');
            $table->decimal('after_miniute_quarterday', 10, 2)->default(0)->comment('بعد كم دقيقة تأخير يخصم ربع يوم');
            $table->decimal('after_time_half_daycut', 10, 2)->default(0)->comment('بعد كم مرة تأخير يخصم نصف يوم');
            $table->decimal('after_time_allday_daycut', 10, 2)->default(0)->comment('بعد كم مرة تأخير يخصم يوم كامل');
            
            // Vacation Policies
            $table->decimal('monthly_vacation_balance', 10, 2)->default(0)->comment('رصيد الإجازات الشهري');
            $table->decimal('after_days_begin_vacation', 10, 2)->default(0)->comment('بعد كم يوم يبدأ حساب الإجازة');
            $table->decimal('first_balance_begin_vacation', 10, 2)->default(0)->comment('الرصيد الأولي المرحل');
            
            // Absence Sanctions
            $table->decimal('sanctions_value_first_abcence', 10, 2)->default(0)->comment('خصم الغياب الأول');
            $table->decimal('sanctions_value_second_abcence', 10, 2)->default(0)->comment('خصم الغياب الثاني');
            $table->decimal('sanctions_value_third_abcence', 10, 2)->default(0)->comment('خصم الغياب الثالث');
            $table->decimal('sanctions_value_fourth_abcence', 10, 2)->default(0)->comment('خصم الغياب الرابع');
            
            // Auditing
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexing
            $table->index('company_id');
            $table->index('created_at');
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
