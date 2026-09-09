<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Plans Table
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->integer('max_users')->default(5); // 0 or null for unlimited/custom
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Dynamic Features Table
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['boolean', 'number', 'text'])->default('boolean');
            $table->string('unit')->nullable(); // e.g. "مستخدم", "جيجابايت", "فرع"
            $table->text('description')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Plan Features Values Pivot
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('feature_id')->constrained('features')->onDelete('cascade');
            $table->string('value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });

        // 4. Subscriptions History Table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null means lifetime or continuous
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime', 'free', 'custom'])->default('free');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['active', 'expired', 'canceled', 'trial'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // Seed initial standard plans and features
        $this->seedInitialData();
    }

    private function seedInitialData(): void
    {
        $now = now();

        // 1. Initial Plans
        $plans = [
            [
                'id' => 1,
                'name' => 'الباقة المجانية',
                'slug' => 'free',
                'description' => 'باقة للمنشآت الناشئة وتجربة النظام بعدد مستخدمين محدود.',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_users' => 5,
                'is_active' => 1,
                'is_popular' => 0,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'الباقة الأساسية',
                'slug' => 'basic',
                'description' => 'للشركات الصغيرة والمتوسطة التي تحتاج إلى إدارة فرق العمل.',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'max_users' => 15,
                'is_active' => 1,
                'is_popular' => 0,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'الباقة الممتازة',
                'slug' => 'premium',
                'description' => 'الخيار الأفضل للشركات المتنامية مع مزايا متقدمة ودعم فني.',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'max_users' => 50,
                'is_active' => 1,
                'is_popular' => 1,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'الباقة الاحترافية',
                'slug' => 'pro',
                'description' => 'للشركات الكبيرة التي تتطلب إدارة شاملة لعدد كبير من الموظفين.',
                'price_monthly' => 149.00,
                'price_yearly' => 1490.00,
                'max_users' => 100,
                'is_active' => 1,
                'is_popular' => 0,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => 'باقة مخصصة',
                'slug' => 'custom',
                'description' => 'حلول مصممة خصيصاً للمؤسسات والشركات العملاقة بدون حدود.',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_users' => 999999,
                'is_active' => 1,
                'is_popular' => 0,
                'sort_order' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('plans')->insert($plans);

        // 2. Initial Features
        $features = [
            [
                'id' => 1,
                'name' => 'إدارة الموظفين والرواتب',
                'code' => 'payroll_management',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'إدارة سجلات الموظفين والرواتب والبدلات والخصومات.',
                'default_value' => '1',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'الربط مع أجهزة البصمة (BioTime)',
                'code' => 'biotime_integration',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'المزامنة التلقائية مع أجهزة البصمة واستيراد الحركات الحيوية.',
                'default_value' => '1',
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'الحد الأقصى للفروع',
                'code' => 'max_branches',
                'type' => 'number',
                'unit' => 'فرع',
                'description' => 'عدد الفروع المسموح بإنشائها لكل شركة.',
                'default_value' => '1',
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'مساحة تخزين الملفات والمستندات',
                'code' => 'storage_space_mb',
                'type' => 'number',
                'unit' => 'ميغابايت',
                'description' => 'المساحة السحابية المخصصة لأرشفة ملفات الموظفين.',
                'default_value' => '500',
                'is_active' => 1,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => 'تقارير مالية وتحليلات متقدمة',
                'code' => 'advanced_reports',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'تقارير تفصيلية ورسوم بيانية لأداء الموظفين والتكاليف.',
                'default_value' => '0',
                'is_active' => 1,
                'sort_order' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'name' => 'مستوى الدعم الفني',
                'code' => 'support_level',
                'type' => 'text',
                'unit' => null,
                'description' => 'مستوى ونوعية الدعم الفني المخصص للباقة.',
                'default_value' => 'بريد إلكتروني',
                'is_active' => 1,
                'sort_order' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('features')->insert($features);

        // 3. Bind Feature Values to Plans
        $planFeatureValues = [
            // Free Plan (ID 1)
            ['plan_id' => 1, 'feature_id' => 1, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 1, 'feature_id' => 2, 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 1, 'feature_id' => 3, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 1, 'feature_id' => 4, 'value' => '200', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 1, 'feature_id' => 5, 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 1, 'feature_id' => 6, 'value' => 'مجتمعي / بريد', 'created_at' => $now, 'updated_at' => $now],

            // Basic Plan (ID 2)
            ['plan_id' => 2, 'feature_id' => 1, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 2, 'feature_id' => 2, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 2, 'feature_id' => 3, 'value' => '2', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 2, 'feature_id' => 4, 'value' => '1000', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 2, 'feature_id' => 5, 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 2, 'feature_id' => 6, 'value' => 'بريد إلكتروني خلال 48 ساعة', 'created_at' => $now, 'updated_at' => $now],

            // Premium Plan (ID 3)
            ['plan_id' => 3, 'feature_id' => 1, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 3, 'feature_id' => 2, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 3, 'feature_id' => 3, 'value' => '5', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 3, 'feature_id' => 4, 'value' => '5000', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 3, 'feature_id' => 5, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 3, 'feature_id' => 6, 'value' => 'أولوية دعم 24/7 عبر المحادثة', 'created_at' => $now, 'updated_at' => $now],

            // Pro Plan (ID 4)
            ['plan_id' => 4, 'feature_id' => 1, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 4, 'feature_id' => 2, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 4, 'feature_id' => 3, 'value' => '15', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 4, 'feature_id' => 4, 'value' => '20000', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 4, 'feature_id' => 5, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 4, 'feature_id' => 6, 'value' => 'مدير حساب مخصص + دعم فوري', 'created_at' => $now, 'updated_at' => $now],

            // Custom Plan (ID 5)
            ['plan_id' => 5, 'feature_id' => 1, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 5, 'feature_id' => 2, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 5, 'feature_id' => 3, 'value' => '999', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 5, 'feature_id' => 4, 'value' => '100000', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 5, 'feature_id' => 5, 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['plan_id' => 5, 'feature_id' => 6, 'value' => 'دعم VIP مخصص على مدار الساعة', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('plan_features')->insert($planFeatureValues);

        // 4. Attach Free Plan to existing companies if any exist
        $existingCompanies = DB::table('companies')->get();
        foreach ($existingCompanies as $comp) {
            DB::table('subscriptions')->insert([
                'company_id' => $comp->id,
                'plan_id' => 1, // Free Plan
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'billing_cycle' => 'free',
                'price' => 0.00,
                'currency' => 'USD',
                'status' => 'active',
                'notes' => 'اشتراك تلقائي في الباقة المجانية عند تهيئة النظام',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
    }
};
