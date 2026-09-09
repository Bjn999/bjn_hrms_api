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
            ['name' => 'view_dashboard', 'group' => 'dashboard', 'label_ar' => 'عرض لوحة القيادة (الداشبورد الشخصي)', 'label_en' => 'View Dashboard'],
            ['name' => 'view_company_dashboard', 'group' => 'dashboard', 'label_ar' => 'عرض الإحصائيات العامة للشركة في الداشبورد', 'label_en' => 'View Company Dashboard Stats'],

            // Employees Management
            ['name' => 'view_employees', 'group' => 'employees', 'label_ar' => 'عرض الموظفين والسجلات', 'label_en' => 'View Employees'],
            ['name' => 'create_employees', 'group' => 'employees', 'label_ar' => 'إضافة موظف جديد', 'label_en' => 'Create Employee'],
            ['name' => 'edit_employees', 'group' => 'employees', 'label_ar' => 'تعديل بيانات موظف', 'label_en' => 'Edit Employee'],
            ['name' => 'delete_employees', 'group' => 'employees', 'label_ar' => 'حذف موظف', 'label_en' => 'Delete Employee'],
            ['name' => 'toggle_employee_login', 'group' => 'employees', 'label_ar' => 'تفعيل/تعطيل حساب الموظف', 'label_en' => 'Toggle Employee Login'],
            ['name' => 'edit_employee_financials', 'group' => 'employees', 'label_ar' => 'إدارة البيانات المالية والبدلات الثابتة للموظف', 'label_en' => 'Manage Employee Financials'],

            // Attendance & Fingerprint
            ['name' => 'view_attendances', 'group' => 'attendances', 'label_ar' => 'عرض سجلات البصمة والحضور', 'label_en' => 'View Attendances'],
            ['name' => 'create_attendances', 'group' => 'attendances', 'label_ar' => 'تسجيل حضور يدوي جديد', 'label_en' => 'Create Attendance'],
            ['name' => 'edit_attendances', 'group' => 'attendances', 'label_ar' => 'تعديل سجلات وبصمات الحضور', 'label_en' => 'Edit Attendance'],
            ['name' => 'delete_attendances', 'group' => 'attendances', 'label_ar' => 'حذف سجلات الحضور اليدوي', 'label_en' => 'Delete Attendance'],
            ['name' => 'sync_biotime', 'group' => 'attendances', 'label_ar' => 'مزامنة أجهزة البصمة (BioTime)', 'label_en' => 'Sync BioTime Devices'],
            ['name' => 'view_shifts', 'group' => 'attendances', 'label_ar' => 'عرض شفتات ومواعيد العمل', 'label_en' => 'View Shifts'],
            ['name' => 'create_shifts', 'group' => 'attendances', 'label_ar' => 'إنشاء شفت عمل جديد', 'label_en' => 'Create Shift'],
            ['name' => 'edit_shifts', 'group' => 'attendances', 'label_ar' => 'تعديل شفت عمل', 'label_en' => 'Edit Shift'],
            ['name' => 'delete_shifts', 'group' => 'attendances', 'label_ar' => 'حذف شفت عمل', 'label_en' => 'Delete Shift'],

            // Salaries & Payroll
            ['name' => 'view_salaries', 'group' => 'salaries', 'label_ar' => 'عرض مسيرات وكشوفات الرواتب', 'label_en' => 'View Salary Payrolls'],
            ['name' => 'view_own_salary', 'group' => 'salaries', 'label_ar' => 'عرض الراتب الشخصي وقسيمة المرتب فقط', 'label_en' => 'View Own Salary Slip'],
            ['name' => 'create_salaries', 'group' => 'salaries', 'label_ar' => 'فتح الشهر واحتساب وتوليد الرواتب', 'label_en' => 'Create & Calculate Salaries'],
            ['name' => 'edit_salaries', 'group' => 'salaries', 'label_ar' => 'تعديل وإيقاف واستئناف صرف الرواتب', 'label_en' => 'Edit Salaries'],
            ['name' => 'delete_salaries', 'group' => 'salaries', 'label_ar' => 'حذف مسير أو سجل راتب موظف', 'label_en' => 'Delete Salaries'],
            ['name' => 'archive_salaries', 'group' => 'salaries', 'label_ar' => 'أرشفة واعتماد وإغلاق الشهر المالي', 'label_en' => 'Archive Salaries'],
            ['name' => 'view_salary_slips', 'group' => 'salaries', 'label_ar' => 'طباعة وقسائم الرواتب (مفردات المرتب)', 'label_en' => 'View Salary Slips'],

            // Financials - Monthly Loans
            ['name' => 'view_loans', 'group' => 'financials', 'label_ar' => 'عرض السلف والقروض الشهرية', 'label_en' => 'View Monthly Loans'],
            ['name' => 'create_loans', 'group' => 'financials', 'label_ar' => 'إضافة سلفة شهرية جديدة', 'label_en' => 'Create Monthly Loan'],
            ['name' => 'edit_loans', 'group' => 'financials', 'label_ar' => 'تعديل سلفة شهرية', 'label_en' => 'Edit Monthly Loan'],
            ['name' => 'delete_loans', 'group' => 'financials', 'label_ar' => 'حذف سلفة شهرية', 'label_en' => 'Delete Monthly Loan'],

            // Financials - Permanent Loans
            ['name' => 'view_permanent_loans', 'group' => 'financials', 'label_ar' => 'عرض السلف المستمرة طويلة الأجل', 'label_en' => 'View Permanent Loans'],
            ['name' => 'create_permanent_loans', 'group' => 'financials', 'label_ar' => 'إنشاء وجدولة سلفة مستمرة', 'label_en' => 'Create Permanent Loan'],
            ['name' => 'edit_permanent_loans', 'group' => 'financials', 'label_ar' => 'تعديل خطة السلفة المستمرة', 'label_en' => 'Edit Permanent Loan'],
            ['name' => 'delete_permanent_loans', 'group' => 'financials', 'label_ar' => 'إلغاء وحذف السلفة المستمرة', 'label_en' => 'Delete Permanent Loan'],
            ['name' => 'disburse_permanent_loans', 'group' => 'financials', 'label_ar' => 'اعتماد وصرف السلفة المستمرة', 'label_en' => 'Disburse Permanent Loan'],
            ['name' => 'pay_permanent_loans', 'group' => 'financials', 'label_ar' => 'تسديد أقساط السلفة المستمرة نقداً', 'label_en' => 'Pay Permanent Loan Installment'],

            // Financials - Sanctions
            ['name' => 'view_sanctions', 'group' => 'financials', 'label_ar' => 'عرض سجل الجزاءات والعقوبات', 'label_en' => 'View Sanctions'],
            ['name' => 'create_sanctions', 'group' => 'financials', 'label_ar' => 'إضافة وتطبيق جزاء أو عقوبة', 'label_en' => 'Create Sanction'],
            ['name' => 'edit_sanctions', 'group' => 'financials', 'label_ar' => 'تعديل بيانات الجزاء', 'label_en' => 'Edit Sanction'],
            ['name' => 'delete_sanctions', 'group' => 'financials', 'label_ar' => 'إلغاء وحذف الجزاء', 'label_en' => 'Delete Sanction'],

            // Financials - Discounts
            ['name' => 'view_discounts', 'group' => 'financials', 'label_ar' => 'عرض الخصومات الاستقطاعية', 'label_en' => 'View Discounts'],
            ['name' => 'create_discounts', 'group' => 'financials', 'label_ar' => 'إضافة خصم مالي مباشر', 'label_en' => 'Create Discount'],
            ['name' => 'edit_discounts', 'group' => 'financials', 'label_ar' => 'تعديل الخصم المالي', 'label_en' => 'Edit Discount'],
            ['name' => 'delete_discounts', 'group' => 'financials', 'label_ar' => 'حذف الخصم المالي', 'label_en' => 'Delete Discount'],

            // Financials - Absences
            ['name' => 'view_absences', 'group' => 'financials', 'label_ar' => 'عرض سجلات وأيام الغياب', 'label_en' => 'View Absences'],
            ['name' => 'create_absences', 'group' => 'financials', 'label_ar' => 'تسجيل يوم غياب على موظف', 'label_en' => 'Create Absence'],
            ['name' => 'edit_absences', 'group' => 'financials', 'label_ar' => 'تعديل بيانات يوم الغياب', 'label_en' => 'Edit Absence'],
            ['name' => 'delete_absences', 'group' => 'financials', 'label_ar' => 'حذف وإلغاء تسجيل الغياب', 'label_en' => 'Delete Absence'],

            // Financials - Rewards
            ['name' => 'view_rewards', 'group' => 'financials', 'label_ar' => 'عرض المكافآت والحوافز المالية', 'label_en' => 'View Financial Rewards'],
            ['name' => 'create_rewards', 'group' => 'financials', 'label_ar' => 'إضافة وصرف مكافأة مالية', 'label_en' => 'Create Reward'],
            ['name' => 'edit_rewards', 'group' => 'financials', 'label_ar' => 'تعديل المكافأة المالية', 'label_en' => 'Edit Reward'],
            ['name' => 'delete_rewards', 'group' => 'financials', 'label_ar' => 'حذف المكافأة المالية', 'label_en' => 'Delete Reward'],

            // Financials - Additions (Overtime)
            ['name' => 'view_additions', 'group' => 'financials', 'label_ar' => 'عرض مستحقات وساعات الإضافي', 'label_en' => 'View Additions'],
            ['name' => 'create_additions', 'group' => 'financials', 'label_ar' => 'تسجيل ساعات وأيام إضافي', 'label_en' => 'Create Addition'],
            ['name' => 'edit_additions', 'group' => 'financials', 'label_ar' => 'تعديل ساعات وأيام الإضافي', 'label_en' => 'Edit Addition'],
            ['name' => 'delete_additions', 'group' => 'financials', 'label_ar' => 'حذف سجل الإضافي', 'label_en' => 'Delete Addition'],

            // Financials - Allowances
            ['name' => 'view_allowances', 'group' => 'financials', 'label_ar' => 'عرض البدلات المالية المتغيرة والثابتة', 'label_en' => 'View Allowances'],
            ['name' => 'create_allowances', 'group' => 'financials', 'label_ar' => 'إضافة وتخصيص بدل لموظف', 'label_en' => 'Create Allowance'],
            ['name' => 'edit_allowances', 'group' => 'financials', 'label_ar' => 'تعديل البدل المالي للموظف', 'label_en' => 'Edit Allowance'],
            ['name' => 'delete_allowances', 'group' => 'financials', 'label_ar' => 'حذف البدل المالي', 'label_en' => 'Delete Allowance'],

            // Financials - Types (Reward, Discount, Allowance Types)
            ['name' => 'view_reward_types', 'group' => 'financials', 'label_ar' => 'عرض أنواع المكافآت', 'label_en' => 'View Reward Types'],
            ['name' => 'create_reward_types', 'group' => 'financials', 'label_ar' => 'إضافة نوع مكافأة جديد', 'label_en' => 'Create Reward Type'],
            ['name' => 'edit_reward_types', 'group' => 'financials', 'label_ar' => 'تعديل نوع مكافأة', 'label_en' => 'Edit Reward Type'],
            ['name' => 'delete_reward_types', 'group' => 'financials', 'label_ar' => 'حذف نوع مكافأة', 'label_en' => 'Delete Reward Type'],

            ['name' => 'view_discount_types', 'group' => 'financials', 'label_ar' => 'عرض أنواع الخصومات', 'label_en' => 'View Discount Types'],
            ['name' => 'create_discount_types', 'group' => 'financials', 'label_ar' => 'إضافة نوع خصم جديد', 'label_en' => 'Create Discount Type'],
            ['name' => 'edit_discount_types', 'group' => 'financials', 'label_ar' => 'تعديل نوع خصم', 'label_en' => 'Edit Discount Type'],
            ['name' => 'delete_discount_types', 'group' => 'financials', 'label_ar' => 'حذف نوع خصم', 'label_en' => 'Delete Discount Type'],

            ['name' => 'view_allowance_types', 'group' => 'financials', 'label_ar' => 'عرض أنواع البدلات', 'label_en' => 'View Allowance Types'],
            ['name' => 'create_allowance_types', 'group' => 'financials', 'label_ar' => 'إضافة نوع بدل جديد', 'label_en' => 'Create Allowance Type'],
            ['name' => 'edit_allowance_types', 'group' => 'financials', 'label_ar' => 'تعديل نوع بدل', 'label_en' => 'Edit Allowance Type'],
            ['name' => 'delete_allowance_types', 'group' => 'financials', 'label_ar' => 'حذف نوع بدل', 'label_en' => 'Delete Allowance Type'],

            // Structure & Settings
            ['name' => 'view_settings', 'group' => 'settings', 'label_ar' => 'عرض بيانات وإعدادات الشركة', 'label_en' => 'View Settings'],
            ['name' => 'edit_settings', 'group' => 'settings', 'label_ar' => 'تعديل إعدادات النظام والشركة واللوائح', 'label_en' => 'Edit Settings'],

            ['name' => 'view_finance_calendars', 'group' => 'settings', 'label_ar' => 'عرض السنوات والشهور المالية', 'label_en' => 'View Finance Calendars'],
            ['name' => 'create_finance_calendars', 'group' => 'settings', 'label_ar' => 'إضافة سنة مالية جديدة', 'label_en' => 'Create Finance Calendar'],
            ['name' => 'edit_finance_calendars', 'group' => 'settings', 'label_ar' => 'تعديل وفتح السنة والشهور المالية', 'label_en' => 'Edit Finance Calendar'],
            ['name' => 'delete_finance_calendars', 'group' => 'settings', 'label_ar' => 'حذف سنة مالية', 'label_en' => 'Delete Finance Calendar'],

            ['name' => 'view_branches', 'group' => 'settings', 'label_ar' => 'عرض الفروع والمواقع', 'label_en' => 'View Branches'],
            ['name' => 'create_branches', 'group' => 'settings', 'label_ar' => 'إضافة فرع جديد', 'label_en' => 'Create Branch'],
            ['name' => 'edit_branches', 'group' => 'settings', 'label_ar' => 'تعديل بيانات فرع', 'label_en' => 'Edit Branch'],
            ['name' => 'delete_branches', 'group' => 'settings', 'label_ar' => 'حذف فرع', 'label_en' => 'Delete Branch'],

            ['name' => 'view_departments', 'group' => 'settings', 'label_ar' => 'عرض الأقسام والإدارات', 'label_en' => 'View Departments'],
            ['name' => 'create_departments', 'group' => 'settings', 'label_ar' => 'إضافة قسم جديد', 'label_en' => 'Create Department'],
            ['name' => 'edit_departments', 'group' => 'settings', 'label_ar' => 'تعديل قسم', 'label_en' => 'Edit Department'],
            ['name' => 'delete_departments', 'group' => 'settings', 'label_ar' => 'حذف قسم', 'label_en' => 'Delete Department'],

            ['name' => 'view_jobs_categories', 'group' => 'settings', 'label_ar' => 'عرض الوظائف والتصنيفات المهنية', 'label_en' => 'View Job Categories'],
            ['name' => 'create_jobs_categories', 'group' => 'settings', 'label_ar' => 'إضافة وظيفة جديدة', 'label_en' => 'Create Job Category'],
            ['name' => 'edit_jobs_categories', 'group' => 'settings', 'label_ar' => 'تعديل وظيفة', 'label_en' => 'Edit Job Category'],
            ['name' => 'delete_jobs_categories', 'group' => 'settings', 'label_ar' => 'حذف وظيفة', 'label_en' => 'Delete Job Category'],

            ['name' => 'view_qualifications', 'group' => 'settings', 'label_ar' => 'عرض المؤهلات العلمية', 'label_en' => 'View Qualifications'],
            ['name' => 'create_qualifications', 'group' => 'settings', 'label_ar' => 'إضافة مؤهل جديد', 'label_en' => 'Create Qualification'],
            ['name' => 'edit_qualifications', 'group' => 'settings', 'label_ar' => 'تعديل مؤهل', 'label_en' => 'Edit Qualification'],
            ['name' => 'delete_qualifications', 'group' => 'settings', 'label_ar' => 'حذف مؤهل', 'label_en' => 'Delete Qualification'],

            ['name' => 'view_occasions', 'group' => 'settings', 'label_ar' => 'عرض المناسبات والعطلات الرسمية', 'label_en' => 'View Occasions'],
            ['name' => 'create_occasions', 'group' => 'settings', 'label_ar' => 'إضافة مناسبة أو عطلة جديدة', 'label_en' => 'Create Occasion'],
            ['name' => 'edit_occasions', 'group' => 'settings', 'label_ar' => 'تعديل مناسبة', 'label_en' => 'Edit Occasion'],
            ['name' => 'delete_occasions', 'group' => 'settings', 'label_ar' => 'حذف مناسبة', 'label_en' => 'Delete Occasion'],

            ['name' => 'view_resignations', 'group' => 'settings', 'label_ar' => 'عرض طلبات الاستقالة وترك العمل', 'label_en' => 'View Resignations'],
            ['name' => 'create_resignations', 'group' => 'settings', 'label_ar' => 'إضافة نوع استقالة جديد', 'label_en' => 'Create Resignation'],
            ['name' => 'edit_resignations', 'group' => 'settings', 'label_ar' => 'تعديل نوع استقالة', 'label_en' => 'Edit Resignation'],
            ['name' => 'delete_resignations', 'group' => 'settings', 'label_ar' => 'حذف نوع استقالة', 'label_en' => 'Delete Resignation'],

            ['name' => 'view_general_constants', 'group' => 'settings', 'label_ar' => 'عرض الثوابت العامة (الجنسيات، اللغات، ...)', 'label_en' => 'View General Constants'],
            ['name' => 'create_general_constants', 'group' => 'settings', 'label_ar' => 'إضافة ثابت عام جديد', 'label_en' => 'Create General Constant'],
            ['name' => 'edit_general_constants', 'group' => 'settings', 'label_ar' => 'تعديل ثابت عام', 'label_en' => 'Edit General Constant'],
            ['name' => 'delete_general_constants', 'group' => 'settings', 'label_ar' => 'حذف ثابت عام', 'label_en' => 'Delete General Constant'],

            // Roles & Permissions
            ['name' => 'view_roles', 'group' => 'roles', 'label_ar' => 'عرض الأدوار والمسميات الوظيفية', 'label_en' => 'View Roles'],
            ['name' => 'create_roles', 'group' => 'roles', 'label_ar' => 'إنشاء دور جديد وتحديد صلاحياته', 'label_en' => 'Create Role'],
            ['name' => 'edit_roles', 'group' => 'roles', 'label_ar' => 'تعديل الأدوار والصلاحيات المرتبطة', 'label_en' => 'Edit Role'],
            ['name' => 'delete_roles', 'group' => 'roles', 'label_ar' => 'حذف دور', 'label_en' => 'Delete Role'],
            ['name' => 'assign_roles', 'group' => 'roles', 'label_ar' => 'إسناد وتغيير أدوار الموظفين', 'label_en' => 'Assign Roles'],

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
