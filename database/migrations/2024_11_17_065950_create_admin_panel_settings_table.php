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
        Schema::create('admin_panel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 250);
            $table->tinyInteger('system_status')->default('1')->comment('حالة النظام: واحد مفعل - صفر معطل');
            $table->string('image', 250)->nullable();
            $table->string('phones', 250);
            $table->string('address', 250);
            $table->string('email', 100);
            $table->integer('added_by');
            $table->integer('updated_by')->nullable();
            $table->integer('com_code');
            $table->decimal('after_miniute_calculate_delay',10,2)->default(0)->comment('بعد كم دقيقة نحتسب تأخير حضور');
            $table->decimal('after_miniute_calculate_early_departure',10,2)->default(0)->comment('بعد كم دقيقة نحتسب انصراف مبكر');
            $table->decimal('after_miniute_quarterday',10,2)->default(0)->comment('بعد كم دقيقة مجموع الحضور المتأخر أو الإنصراف المبكر نخصم ربع يوم');
            $table->decimal('after_time_half_daycut',10,2)->default(0)->comment('بعد كم مرة تأخير أو إنصراف مبكر نخصم نصف يوم');
            $table->decimal('after_time_allday_daycut',10,2)->default(0)->comment('بعد كم مرة تأخير أو إنصراف مبكر نخصم يوم كامل');
            $table->decimal('monthly_vacation_balance',10,2)->default(0)->comment('رصيد إجازات الموظف الشهري');
            $table->decimal('after_days_begin_vacation',10,2)->default(0)->comment('بعد كم يوم ينزل للموظف رصيد إجازات');
            $table->decimal('first_balance_begin_vacation',10,2)->default(0)->comment('الرصيد الأولي المرحل عند تفعيل الاجازات للموظف (مثل نزول عشرة أيام ونص بعد ستة شهور للموظف)');
            $table->decimal('sanctions_value_first_abcence',10,2)->default(0)->comment('قيمة خصم الأيام بعد أول غياب بدون عذر (أيام)');
            $table->decimal('sanctions_value_second_abcence',10,2)->default(0)->comment('قيمة خصم الأيام بعد ثاني غياب بدون عذر (أيام)');
            $table->decimal('sanctions_value_third_abcence',10,2)->default(0)->comment('قيمة خصم الأيام بعد ثالث غياب بدون عذر (أيام)');
            $table->decimal('sanctions_value_fourth_abcence',10,2)->default(0)->comment('قيمة خصم الأيام بعد رابع غياب بدون عذر (أيام)');

            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_panel_settings');
    }
};
