<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Features Catalog
        $features = [
            [
                'id' => 1,
                'name' => 'إدارة الموظفين والرواتب',
                'code' => 'payroll_management',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'إدارة سجلات الموظفين والرواتب والبدلات والخصومات.',
                'default_value' => '1',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'name' => 'الربط مع أجهزة البصمة (BioTime)',
                'code' => 'biotime_integration',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'المزامنة التلقائية مع أجهزة البصمة واستيراد الحركات الحيوية.',
                'default_value' => '1',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'name' => 'الحد الأقصى للفروع',
                'code' => 'max_branches',
                'type' => 'number',
                'unit' => 'فرع',
                'description' => 'عدد الفروع المسموح بإنشائها لكل شركة.',
                'default_value' => '1',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'id' => 4,
                'name' => 'مساحة تخزين الملفات والمستندات',
                'code' => 'storage_space_mb',
                'type' => 'number',
                'unit' => 'ميغابايت',
                'description' => 'المساحة السحابية المخصصة لأرشفة ملفات الموظفين.',
                'default_value' => '500',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'id' => 5,
                'name' => 'تقارير مالية وتحليلات متقدمة',
                'code' => 'advanced_reports',
                'type' => 'boolean',
                'unit' => null,
                'description' => 'تقارير تفصيلية ورسوم بيانية لأداء الموظفين والتكاليف.',
                'default_value' => '0',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'id' => 6,
                'name' => 'مستوى الدعم الفني',
                'code' => 'support_level',
                'type' => 'text',
                'unit' => null,
                'description' => 'مستوى ونوعية الدعم الفني المخصص للباقة.',
                'default_value' => 'بريد إلكتروني',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['id' => $f['id']], $f);
        }

        // 2. Seed Subscription Plans
        $plans = [
            [
                'id' => 1,
                'name' => 'الباقة المجانية',
                'slug' => 'free',
                'description' => 'باقة للمنشآت الناشئة وتجربة النظام بعدد مستخدمين محدود.',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_users' => 5,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'name' => 'الباقة الأساسية',
                'slug' => 'basic',
                'description' => 'للشركات الصغيرة والمتوسطة التي تحتاج إلى إدارة فرق العمل.',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'max_users' => 20,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'name' => 'الباقة الممتازة',
                'slug' => 'premium',
                'description' => 'الخيار الأفضل للشركات المتنامية مع مزايا متقدمة ودعم فني.',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'max_users' => 100,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 3,
            ],
            [
                'id' => 4,
                'name' => 'الباقة الاحترافية',
                'slug' => 'pro',
                'description' => 'للشركات الكبيرة التي تتطلب إدارة شاملة لعدد كبير من الموظفين.',
                'price_monthly' => 149.00,
                'price_yearly' => 1490.00,
                'max_users' => 200,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['id' => $p['id']], $p);
        }

        // 3. Seed Plan Feature Values
        $planFeatures = [
            // Plan 1: Free
            ['plan_id' => 1, 'feature_id' => 1, 'value' => '1'],
            ['plan_id' => 1, 'feature_id' => 2, 'value' => '0'],
            ['plan_id' => 1, 'feature_id' => 3, 'value' => '1'],
            ['plan_id' => 1, 'feature_id' => 4, 'value' => '200'],
            ['plan_id' => 1, 'feature_id' => 5, 'value' => '0'],
            ['plan_id' => 1, 'feature_id' => 6, 'value' => 'مجتمعي / بريد'],

            // Plan 2: Basic
            ['plan_id' => 2, 'feature_id' => 1, 'value' => '1'],
            ['plan_id' => 2, 'feature_id' => 2, 'value' => '0'],
            ['plan_id' => 2, 'feature_id' => 3, 'value' => '2'],
            ['plan_id' => 2, 'feature_id' => 4, 'value' => '1000'],
            ['plan_id' => 2, 'feature_id' => 5, 'value' => '1'],
            ['plan_id' => 2, 'feature_id' => 6, 'value' => 'بريد إلكتروني خلال 48 ساعة'],

            // Plan 3: Premium
            ['plan_id' => 3, 'feature_id' => 1, 'value' => '1'],
            ['plan_id' => 3, 'feature_id' => 2, 'value' => '1'],
            ['plan_id' => 3, 'feature_id' => 3, 'value' => '5'],
            ['plan_id' => 3, 'feature_id' => 4, 'value' => '5000'],
            ['plan_id' => 3, 'feature_id' => 5, 'value' => '1'],
            ['plan_id' => 3, 'feature_id' => 6, 'value' => 'أولوية دعم 24/7 عبر المحادثة'],

            // Plan 4: Pro
            ['plan_id' => 4, 'feature_id' => 1, 'value' => '1'],
            ['plan_id' => 4, 'feature_id' => 2, 'value' => '1'],
            ['plan_id' => 4, 'feature_id' => 3, 'value' => '15'],
            ['plan_id' => 4, 'feature_id' => 4, 'value' => '20000'],
            ['plan_id' => 4, 'feature_id' => 5, 'value' => '1'],
            ['plan_id' => 4, 'feature_id' => 6, 'value' => 'مدير حساب مخصص + دعم فوري'],
        ];

        foreach ($planFeatures as $pf) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $pf['plan_id'], 'feature_id' => $pf['feature_id']],
                ['value' => $pf['value']]
            );
        }
    }
}
