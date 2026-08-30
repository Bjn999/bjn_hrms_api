<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'group' => 'dashboard', 'label_ar' => 'عرض لوحة التحكم (الداشبورد)', 'label_en' => 'View Dashboard'],
            ['name' => 'view_company_dashboard', 'group' => 'dashboard', 'label_ar' => 'عرض الإحصائيات العامة للشركة في الداشبورد', 'label_en' => 'View Company Dashboard Stats'],

            // Employees Management
            ['name' => 'view_employees', 'group' => 'employees', 'label_ar' => 'عرض الموظفين والسجلات', 'label_en' => 'View Employees'],
            ['name' => 'create_employees', 'group' => 'employees', 'label_ar' => 'إضافة موظف جديد', 'label_en' => 'Create Employee'],
            ['name' => 'edit_employees', 'group' => 'employees', 'label_ar' => 'تعديل بيانات موظف', 'label_en' => 'Edit Employee'],
            ['name' => 'delete_employees', 'group' => 'employees', 'label_ar' => 'حذف موظف', 'label_en' => 'Delete Employee'],
            ['name' => 'toggle_employee_login', 'group' => 'employees', 'label_ar' => 'تفعيل/تعطيل حساب الموظف', 'label_en' => 'Toggle Employee Login'],
            ['name' => 'manage_employee_financials', 'group' => 'employees', 'label_ar' => 'إدارة البيانات المالية للموظف', 'label_en' => 'Manage Employee Financials'],

            // Attendance & Fingerprint
            ['name' => 'view_attendances', 'group' => 'attendances', 'label_ar' => 'عرض سجلات البصمة والحضور', 'label_en' => 'View Attendances'],
            ['name' => 'manage_attendances', 'group' => 'attendances', 'label_ar' => 'إدارة وتسجيل الحضور اليدوي', 'label_en' => 'Manage Manual Attendance'],
            ['name' => 'sync_biotime', 'group' => 'attendances', 'label_ar' => 'مزامنة أجهزة البصمة (BioTime)', 'label_en' => 'Sync BioTime Devices'],
            ['name' => 'view_shifts', 'group' => 'attendances', 'label_ar' => 'عرض شفتات ومواعيد العمل', 'label_en' => 'View Shifts'],
            ['name' => 'manage_shifts', 'group' => 'attendances', 'label_ar' => 'إدارة وتعديل شفتات العمل', 'label_en' => 'Manage Shifts'],

            // Salaries & Payroll
            ['name' => 'view_salaries', 'group' => 'salaries', 'label_ar' => 'عرض مسيرات وكشوفات الرواتب', 'label_en' => 'View Salary Payrolls'],
            ['name' => 'view_own_salary', 'group' => 'salaries', 'label_ar' => 'عرض الراتب الشخصي وقسيمة المرتب فقط', 'label_en' => 'View Own Salary Slip'],
            ['name' => 'manage_salaries', 'group' => 'salaries', 'label_ar' => 'إعداد وتعديل واحتساب الرواتب الشهري', 'label_en' => 'Manage Salaries'],
            ['name' => 'archive_salaries', 'group' => 'salaries', 'label_ar' => 'أرشفة واعتماد الأكشوفات المالية', 'label_en' => 'Archive Salaries'],
            ['name' => 'view_salary_slips', 'group' => 'salaries', 'label_ar' => 'طباعة وقسائم الرواتب (مفردات المرتب)', 'label_en' => 'View Salary Slips'],

            // Loans & Financial Actions
            ['name' => 'view_loans', 'group' => 'financials', 'label_ar' => 'عرض السلف والقروض الشهري', 'label_en' => 'View Monthly Loans'],
            ['name' => 'manage_loans', 'group' => 'financials', 'label_ar' => 'إدارة وإنشاء السلف والقروض الشهري', 'label_en' => 'Manage Monthly Loans'],
            ['name' => 'view_permanent_loans', 'group' => 'financials', 'label_ar' => 'عرض السلف المستمرة طويلة الأجل', 'label_en' => 'View Permanent Loans'],
            ['name' => 'manage_permanent_loans', 'group' => 'financials', 'label_ar' => 'إدارة وجدولة السلف المستمرة', 'label_en' => 'Manage Permanent Loans'],
            ['name' => 'view_sanctions', 'group' => 'financials', 'label_ar' => 'عرض سجل الجزاءات والعقوبات', 'label_en' => 'View Sanctions'],
            ['name' => 'manage_sanctions', 'group' => 'financials', 'label_ar' => 'إدارة وتطبيق الجزاءات والعقوبات', 'label_en' => 'Manage Sanctions'],
            ['name' => 'view_discounts', 'group' => 'financials', 'label_ar' => 'عرض الخصومات الاستقطاعية', 'label_en' => 'View Discounts'],
            ['name' => 'manage_discounts', 'group' => 'financials', 'label_ar' => 'إدارة وإنشاء الخصومات المباشرة', 'label_en' => 'Manage Discounts'],
            ['name' => 'view_absences', 'group' => 'financials', 'label_ar' => 'عرض سجلات وأيام الغياب', 'label_en' => 'View Absences'],
            ['name' => 'manage_absences', 'group' => 'financials', 'label_ar' => 'إدارة وتسجيل أيام الغياب', 'label_en' => 'Manage Absences'],
            ['name' => 'view_rewards', 'group' => 'financials', 'label_ar' => 'عرض المكافآت والحوافز المالية', 'label_en' => 'View Financial Rewards'],
            ['name' => 'manage_rewards', 'group' => 'financials', 'label_ar' => 'إدارة وصرف المكافآت والحوافز', 'label_en' => 'Manage Rewards'],
            ['name' => 'view_additions', 'group' => 'financials', 'label_ar' => 'عرض مستحقات وساعات الإضافي', 'label_en' => 'View Additions'],
            ['name' => 'manage_additions', 'group' => 'financials', 'label_ar' => 'إدارة واعتماد ساعات وأيام الإضافي', 'label_en' => 'Manage Additions'],
            ['name' => 'view_allowances', 'group' => 'financials', 'label_ar' => 'عرض البدلات المالية المتغيرة والثابتة', 'label_en' => 'View Allowances'],
            ['name' => 'manage_allowances', 'group' => 'financials', 'label_ar' => 'إدارة وتعديل البدلات والتعويضات', 'label_en' => 'Manage Allowances'],

            // Structure & Settings
            ['name' => 'view_settings', 'group' => 'settings', 'label_ar' => 'عرض بيانات وإعدادات الشركة', 'label_en' => 'View Settings'],
            ['name' => 'view_company_settings', 'group' => 'settings', 'label_ar' => 'عرض قائمة ضبط إعدادات الشركة والفروع', 'label_en' => 'View Company Settings Group'],
            ['name' => 'manage_settings', 'group' => 'settings', 'label_ar' => 'إدارة وتعديل إعدادات النظام والشركة', 'label_en' => 'Manage Settings'],
            ['name' => 'manage_branches', 'group' => 'settings', 'label_ar' => 'إدارة الفروع والمواقع', 'label_en' => 'Manage Branches'],
            ['name' => 'manage_departments', 'group' => 'settings', 'label_ar' => 'إدارة الأقسام والادارات التنظيمية', 'label_en' => 'Manage Departments'],
            ['name' => 'manage_jobs_categories', 'group' => 'settings', 'label_ar' => 'إدارة الوظائف والتصنيفات المهنية', 'label_en' => 'Manage Jobs Categories'],
            ['name' => 'manage_qualifications', 'group' => 'settings', 'label_ar' => 'إدارة المؤهلات والدرجات العلمية', 'label_en' => 'Manage Qualifications'],
            ['name' => 'manage_occasions', 'group' => 'settings', 'label_ar' => 'إدارة المناسبات والعطلات الرسمية', 'label_en' => 'Manage Occasions'],
            ['name' => 'manage_resignations', 'group' => 'settings', 'label_ar' => 'إدارة الاستقالات وإجراءات ترك العمل', 'label_en' => 'Manage Resignations'],
            ['name' => 'manage_general_constants', 'group' => 'settings', 'label_ar' => 'إدارة الثوابت العامة (الجنسيات، اللغات، ...)', 'label_en' => 'Manage General Constants'],

            // Roles & Permissions
            ['name' => 'view_roles', 'group' => 'roles', 'label_ar' => 'عرض الأدوار والمسميات الوظيفية', 'label_en' => 'View Roles'],
            ['name' => 'manage_roles', 'group' => 'roles', 'label_ar' => 'إدارة وتخصيص الأدوار والصلاحيات', 'label_en' => 'Manage Roles & Permissions'],

            // Reports & Analytics
            ['name' => 'view_employee_reports', 'group' => 'reports', 'label_ar' => 'عرض وتقارير بيانات الموظفين والسجلات', 'label_en' => 'View Employee Reports'],
            ['name' => 'view_attendance_reports', 'group' => 'reports', 'label_ar' => 'عرض وتقارير الحضور والغياب والتأخير', 'label_en' => 'View Attendance Reports'],
            ['name' => 'view_payroll_reports', 'group' => 'reports', 'label_ar' => 'عرض وتقارير مسيرات الرواتب بالتفصيل', 'label_en' => 'View Payroll Reports'],
            ['name' => 'view_financial_reports', 'group' => 'reports', 'label_ar' => 'عرض وتقارير السلف والخصومات والمكافآت', 'label_en' => 'View Financial Reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm['name'], 'web');
        }
    }
}
