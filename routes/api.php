<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// ==========================================
// 1. Unified Auth & Core Controllers
// ==========================================
use App\Http\Controllers\Api\AuthController as UnifiedAuthController;
use App\Http\Controllers\Api\NotificationController as UnifiedNotificationController;

// ==========================================
// 2. Super Admin Controllers (SaaS Platform)
// ==========================================
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\Api\SuperAdmin\PlanController as SuperAdminPlanController;
use App\Http\Controllers\Api\SuperAdmin\FeatureController as SuperAdminFeatureController;
use App\Http\Controllers\Api\SuperAdmin\CompanySubscriptionController as SuperAdminCompanySubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionUpgradeRequestController as SuperAdminSubscriptionUpgradeRequestController;

// ==========================================
// 3. Company Admin / HR Controllers
// ==========================================
use App\Http\Controllers\Api\Company\Admin\AdminPanelSettingsController;
use App\Http\Controllers\Api\Company\Admin\SubscriptionUpgradeRequestController as CompanySubscriptionUpgradeRequestController;
use App\Http\Controllers\Api\Company\Admin\FinanceCalendersController;
use App\Http\Controllers\Api\Company\Admin\BranchesController;
use App\Http\Controllers\Api\Company\Admin\ShiftsController;
use App\Http\Controllers\Api\Company\Admin\DepartmentsController;
use App\Http\Controllers\Api\Company\Admin\JobsCategoriesController;
use App\Http\Controllers\Api\Company\Admin\QualificationsController;
use App\Http\Controllers\Api\Company\Admin\OccasionsController;
use App\Http\Controllers\Api\Company\Admin\ResignationsController;
use App\Http\Controllers\Api\Company\Admin\NationalitiesController;
use App\Http\Controllers\Api\Company\Admin\SalaryRecordsController;
use App\Http\Controllers\Api\Company\Admin\ReligionsController;
use App\Http\Controllers\Api\Company\Admin\BloodGroupsController;
use App\Http\Controllers\Api\Company\Admin\CountriesController;
use App\Http\Controllers\Api\Company\Admin\GovernoratesController;
use App\Http\Controllers\Api\Company\Admin\CentersController;
use App\Http\Controllers\Api\Company\Admin\EmployeesController;
use App\Http\Controllers\Api\Company\Admin\RolesController;
use App\Http\Controllers\Api\Company\Admin\AdditionalSalTypesController;
use App\Http\Controllers\Api\Company\Admin\DiscountSalTypesController;
use App\Http\Controllers\Api\Company\Admin\AllowancesController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeSanctionsController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeAbsencesController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeDiscountsController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeLoansController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeePermanentLoansController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeAdditionsController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeRewardsController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeAllowancesController;
use App\Http\Controllers\Api\Company\Admin\MainSalaryEmployeeController;
use App\Http\Controllers\Api\Company\Admin\LanguagesController;
use App\Http\Controllers\Api\Company\Admin\AttendancesController as AdminAttendancesController;
use App\Http\Controllers\Api\Company\Admin\AttendanceRawLogsController;
use App\Http\Controllers\Api\Company\Admin\VacationTypesController;
use App\Http\Controllers\Api\Company\Admin\VacationBalancesController;
use App\Http\Controllers\Api\Company\Admin\VacationRequestsController;

// ==========================================
// 4. Company Employee Controllers (Web Portal & Mobile App)
// ==========================================
use App\Http\Controllers\Api\Company\Employee\AttendanceController as EmployeeAttendanceController;
use App\Http\Controllers\Api\Company\Employee\DashboardController as EmployeeDashboardController;

// ==========================================
// Public Routes
// ==========================================
Route::post('/login', [UnifiedAuthController::class, 'login']);
Route::post('/register', [UnifiedAuthController::class, 'registerCompany']);
Route::get('/public-plans', function() {
    $plans = \App\Models\Plan::where('is_active', true)
        ->orderBy('sort_order', 'asc')
        ->orderBy('id', 'asc')
        ->get();
    return response()->json(['status' => true, 'data' => $plans]);
});

// ==========================================
// Authenticated Shared & Employee Routes (Web Portal & Mobile)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UnifiedAuthController::class, 'user']);
    Route::get('/user/personal-dashboard', [EmployeeDashboardController::class, 'index'])->middleware('permission:view_dashboard');
    
    // Employee Attendance APIs (Mobile Punch & Web ESS)
    Route::get('/user/attendance/today', [EmployeeAttendanceController::class, 'todayStatus'])->middleware('permission:view_dashboard');
    Route::post('/user/attendance/punch', [EmployeeAttendanceController::class, 'punch'])->middleware('permission:view_dashboard');
    Route::get('/user/attendance/my-records', [EmployeeAttendanceController::class, 'myAttendance'])->middleware('permission:view_dashboard');

    // Vacations (Employee Self-Service)
    Route::get('/user/vacation-types', [VacationTypesController::class, 'index'])->middleware('permission:view_dashboard');
    Route::get('/user/my-vacations', [VacationRequestsController::class, 'myRequests'])->middleware('permission:view_dashboard');
    Route::post('/user/vacation-requests', [VacationRequestsController::class, 'store'])->middleware('permission:view_dashboard');
    Route::post('/user/vacation-requests/{id}/cancel', [VacationRequestsController::class, 'cancel'])->middleware('permission:view_dashboard');
    Route::put('/profile/update', [UnifiedAuthController::class, 'updateProfile']);
    Route::post('/logout', [UnifiedAuthController::class, 'logout']);

    // Broadcasting Auth for API Bearer Tokens
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return Broadcast::auth($request);
    });

    // Notifications (Unified In-App Notifications API)
    Route::get('/notifications', [UnifiedNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [UnifiedNotificationController::class, 'unreadCount']);
    Route::post('/notifications/test-broadcast', [UnifiedNotificationController::class, 'testBroadcast']);
    Route::post('/notifications/{id}/mark-as-read', [UnifiedNotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [UnifiedNotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [UnifiedNotificationController::class, 'destroy']);
});

// ==========================================
// Company Admin / HR Management Routes
// ==========================================
Route::prefix('admin')->group(function() {
    Route::middleware(['auth:sanctum', 'subscription.active'])->group(function () {
        // Settings
        Route::get('/generalSettings', [AdminPanelSettingsController::class, 'index'])->middleware('permission:view_settings');
        Route::post('/generalSettings', [AdminPanelSettingsController::class, 'update'])->middleware('permission:edit_settings');
        Route::post('/companyProfile', [AdminPanelSettingsController::class, 'updateCompanyProfile'])->middleware('permission:edit_settings');
        Route::get('/company-subscription', [AdminPanelSettingsController::class, 'getSubscriptionInfo']);
        Route::get('/subscription-upgrade-requests', [CompanySubscriptionUpgradeRequestController::class, 'index']);
        Route::post('/subscription-upgrade-requests', [CompanySubscriptionUpgradeRequestController::class, 'store']);

        // Finance Calendars
        Route::get('/finance-calendars', [FinanceCalendersController::class, 'index'])->middleware('permission:view_finance_calendars');
        Route::get('/finance-calendars/{id}/months', [FinanceCalendersController::class, 'months'])->middleware('permission:view_finance_calendars');
        Route::post('/finance-calendars', [FinanceCalendersController::class, 'store'])->middleware('permission:create_finance_calendars');
        Route::put('/finance-calendars/{id}', [FinanceCalendersController::class, 'update'])->middleware('permission:edit_finance_calendars');
        Route::post('/finance-calendars/{id}/open', [FinanceCalendersController::class, 'open'])->middleware('permission:edit_finance_calendars');
        Route::delete('/finance-calendars/{id}', [FinanceCalendersController::class, 'destroy'])->middleware('permission:delete_finance_calendars');

        // Branches
        Route::get('/branches', [BranchesController::class, 'index'])->middleware('permission:view_branches|view_employees');
        Route::post('/branches', [BranchesController::class, 'store'])->middleware('permission:create_branches');
        Route::put('/branches/{id}', [BranchesController::class, 'update'])->middleware('permission:edit_branches');
        Route::delete('/branches/{id}', [BranchesController::class, 'destroy'])->middleware('permission:delete_branches');

        // Shifts
        Route::get('/shifts', [ShiftsController::class, 'index'])->middleware('permission:view_shifts|view_employees');
        Route::post('/shifts', [ShiftsController::class, 'store'])->middleware('permission:create_shifts');
        Route::put('/shifts/{id}', [ShiftsController::class, 'update'])->middleware('permission:edit_shifts');
        Route::delete('/shifts/{id}', [ShiftsController::class, 'destroy'])->middleware('permission:delete_shifts');

        // Departments
        Route::get('/departments', [DepartmentsController::class, 'index'])->middleware('permission:view_departments|view_employees');
        Route::post('/departments', [DepartmentsController::class, 'store'])->middleware('permission:create_departments');
        Route::put('/departments/{id}', [DepartmentsController::class, 'update'])->middleware('permission:edit_departments');
        Route::delete('/departments/{id}', [DepartmentsController::class, 'destroy'])->middleware('permission:delete_departments');

        // Jobs Categories
        Route::get('/jobs-categories', [JobsCategoriesController::class, 'index'])->middleware('permission:view_jobs_categories|view_employees');
        Route::post('/jobs-categories', [JobsCategoriesController::class, 'store'])->middleware('permission:create_jobs_categories');
        Route::put('/jobs-categories/{id}', [JobsCategoriesController::class, 'update'])->middleware('permission:edit_jobs_categories');
        Route::delete('/jobs-categories/{id}', [JobsCategoriesController::class, 'destroy'])->middleware('permission:delete_jobs_categories');

        // Qualifications
        Route::get('/qualifications', [QualificationsController::class, 'index'])->middleware('permission:view_qualifications|view_employees');
        Route::post('/qualifications', [QualificationsController::class, 'store'])->middleware('permission:create_qualifications');
        Route::put('/qualifications/{id}', [QualificationsController::class, 'update'])->middleware('permission:edit_qualifications');
        Route::delete('/qualifications/{id}', [QualificationsController::class, 'destroy'])->middleware('permission:delete_qualifications');

        // Occasions
        Route::get('/occasions', [OccasionsController::class, 'index'])->middleware('permission:view_occasions');
        Route::post('/occasions', [OccasionsController::class, 'store'])->middleware('permission:create_occasions');
        Route::put('/occasions/{id}', [OccasionsController::class, 'update'])->middleware('permission:edit_occasions');
        Route::delete('/occasions/{id}', [OccasionsController::class, 'destroy'])->middleware('permission:delete_occasions');

        // Vacation Types
        Route::get('/vacation-types', [VacationTypesController::class, 'index'])->middleware('permission:view_dashboard|view_occasions|view_employees');
        Route::post('/vacation-types', [VacationTypesController::class, 'store'])->middleware('permission:create_occasions|create_employees');
        Route::put('/vacation-types/{id}', [VacationTypesController::class, 'update'])->middleware('permission:edit_occasions|edit_employees');
        Route::delete('/vacation-types/{id}', [VacationTypesController::class, 'destroy'])->middleware('permission:delete_occasions|delete_employees');

        // Vacation Balances
        Route::get('/vacation-balances', [VacationBalancesController::class, 'index'])->middleware('permission:view_occasions|view_employees');
        Route::post('/vacation-balances/recalculate', [VacationBalancesController::class, 'recalculate'])->middleware('permission:edit_occasions|edit_employees');
        Route::put('/vacation-balances/{id}', [VacationBalancesController::class, 'update'])->middleware('permission:edit_occasions|edit_employees');

        // Vacation Requests (Admin / HR / Approvals)
        Route::get('/vacation-requests', [VacationRequestsController::class, 'index'])->middleware('permission:view_dashboard|view_occasions|view_employees');
        Route::post('/vacation-requests', [VacationRequestsController::class, 'store'])->middleware('permission:view_dashboard|create_occasions|create_employees');
        Route::post('/vacation-requests/{id}/approve', [VacationRequestsController::class, 'approve'])->middleware('permission:edit_occasions|edit_employees');
        Route::post('/vacation-requests/{id}/reject', [VacationRequestsController::class, 'reject'])->middleware('permission:edit_occasions|edit_employees');
        Route::delete('/vacation-requests/{id}', [VacationRequestsController::class, 'destroy'])->middleware('permission:view_dashboard|delete_occasions|delete_employees');

        // Resignations
        Route::get('/resignations', [ResignationsController::class, 'index'])->middleware('permission:view_resignations|view_employees');
        Route::post('/resignations', [ResignationsController::class, 'store'])->middleware('permission:create_resignations');
        Route::put('/resignations/{id}', [ResignationsController::class, 'update'])->middleware('permission:edit_resignations');
        Route::delete('/resignations/{id}', [ResignationsController::class, 'destroy'])->middleware('permission:delete_resignations');

        // General Constants (Lookups)
        Route::get('/nationalities', [NationalitiesController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/nationalities', [NationalitiesController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/nationalities/{id}', [NationalitiesController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/nationalities/{id}', [NationalitiesController::class, 'destroy'])->middleware('permission:delete_general_constants');

        Route::get('/religions', [ReligionsController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/religions', [ReligionsController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/religions/{id}', [ReligionsController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/religions/{id}', [ReligionsController::class, 'destroy'])->middleware('permission:delete_general_constants');

        Route::get('/blood-groups', [BloodGroupsController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/blood-groups', [BloodGroupsController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/blood-groups/{id}', [BloodGroupsController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/blood-groups/{id}', [BloodGroupsController::class, 'destroy'])->middleware('permission:delete_general_constants');

        Route::get('/countries', [CountriesController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/countries', [CountriesController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/countries/{id}', [CountriesController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/countries/{id}', [CountriesController::class, 'destroy'])->middleware('permission:delete_general_constants');

        Route::get('/governorates', [GovernoratesController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/governorates', [GovernoratesController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/governorates/{id}', [GovernoratesController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/governorates/{id}', [GovernoratesController::class, 'destroy'])->middleware('permission:delete_general_constants');

        Route::get('/centers', [CentersController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/centers', [CentersController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/centers/{id}', [CentersController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/centers/{id}', [CentersController::class, 'destroy'])->middleware('permission:delete_general_constants');
        Route::get('/governorates-by-country/{country_id}', [CentersController::class, 'getGovernorates'])->middleware('permission:view_general_constants|view_employees');

        Route::get('/languages', [LanguagesController::class, 'index'])->middleware('permission:view_general_constants|view_employees');
        Route::post('/languages', [LanguagesController::class, 'store'])->middleware('permission:create_general_constants');
        Route::put('/languages/{id}', [LanguagesController::class, 'update'])->middleware('permission:edit_general_constants');
        Route::delete('/languages/{id}', [LanguagesController::class, 'destroy'])->middleware('permission:delete_general_constants');

        // Roles & Permissions Management
        Route::get('permissions', [RolesController::class, 'permissionsList'])->middleware('permission:view_roles|view_employees');
        Route::get('roles', [RolesController::class, 'index'])->middleware('permission:view_roles|view_employees');
        Route::get('roles/{id}', [RolesController::class, 'show'])->middleware('permission:view_roles|view_employees');
        Route::post('roles', [RolesController::class, 'store'])->middleware('permission:create_roles');
        Route::put('roles/{id}', [RolesController::class, 'update'])->middleware('permission:edit_roles');
        Route::delete('roles/{id}', [RolesController::class, 'destroy'])->middleware('permission:delete_roles');
        Route::post('users/{userId}/assign-role', [RolesController::class, 'assignRoleToUser'])->middleware('permission:assign_roles');

        // Employees Affairs
        Route::get('employees/required-data', [EmployeesController::class, 'get_required_data'])->middleware('permission:view_employees|create_employees|edit_employees');
        Route::get('employees', [EmployeesController::class, 'index'])->middleware('permission:view_employees');
        Route::get('employees/{id}', [EmployeesController::class, 'show'])->middleware('permission:view_employees');
        Route::post('employees', [EmployeesController::class, 'store'])->middleware('permission:create_employees');
        Route::put('employees/{id}', [EmployeesController::class, 'update'])->middleware('permission:edit_employees');
        Route::delete('employees/{id}', [EmployeesController::class, 'destroy'])->middleware('permission:delete_employees');
        Route::post('employees/{id}/toggle-login', [EmployeesController::class, 'toggleLogin'])->middleware('permission:toggle_employee_login');
        Route::post('employees/{id}/fixed-allowances', [EmployeesController::class, 'addFixedAllowance'])->middleware('permission:edit_employee_financials');
        Route::put('employees/fixed-allowances/{id}', [EmployeesController::class, 'updateFixedAllowance'])->middleware('permission:edit_employee_financials');
        Route::delete('employees/fixed-allowances/{id}', [EmployeesController::class, 'deleteFixedAllowance'])->middleware('permission:edit_employee_financials');
        Route::post('employees/{id}/files', [EmployeesController::class, 'addFile'])->middleware('permission:edit_employees');
        Route::delete('employees/files/{id}', [EmployeesController::class, 'deleteFile'])->middleware('permission:edit_employees');
        Route::get('employees/{id}/salary-archive', [EmployeesController::class, 'getSalaryArchive'])->middleware('permission:edit_employee_financials|view_employees');

        // Attendances & BioTime Management
        Route::get('attendances', [AdminAttendancesController::class, 'index'])->middleware('permission:view_attendances');
        Route::get('attendances/{id}', [AdminAttendancesController::class, 'show'])->middleware('permission:view_attendances');
        Route::get('attendances/{month_id}/employee/{employee_code}', [AdminAttendancesController::class, 'employeeAttendance'])->middleware('permission:view_attendances');
        Route::get('attendance-raw-logs', [AttendanceRawLogsController::class, 'index'])->middleware('permission:view_attendances');
        Route::post('attendances/{month_id}/sync-biotime', [AdminAttendancesController::class, 'syncBioTime'])->middleware('permission:sync_biotime');
        Route::post('attendances/{month_id}/extract', [AdminAttendancesController::class, 'extractMonthActions'])->middleware('permission:create_attendances|edit_attendances');
        Route::post('attendances/{month_id}/employee/{employee_code}/extract', [AdminAttendancesController::class, 'extractEmployeeActions'])->middleware('permission:create_attendances|edit_attendances');

        // Types Catalogs
        Route::get('reward-sal-types', [AdditionalSalTypesController::class, 'index'])->middleware('permission:view_reward_types|view_rewards|view_employees');
        Route::post('reward-sal-types', [AdditionalSalTypesController::class, 'store'])->middleware('permission:create_reward_types');
        Route::put('reward-sal-types/{id}', [AdditionalSalTypesController::class, 'update'])->middleware('permission:edit_reward_types');
        Route::delete('reward-sal-types/{id}', [AdditionalSalTypesController::class, 'destroy'])->middleware('permission:delete_reward_types');

        Route::get('additional-sal-types', [AdditionalSalTypesController::class, 'index'])->middleware('permission:view_additions|view_employees');
        Route::post('additional-sal-types', [AdditionalSalTypesController::class, 'store'])->middleware('permission:create_additions');
        Route::put('additional-sal-types/{id}', [AdditionalSalTypesController::class, 'update'])->middleware('permission:edit_additions');
        Route::delete('additional-sal-types/{id}', [AdditionalSalTypesController::class, 'destroy'])->middleware('permission:delete_additions');

        Route::get('discount-sal-types', [DiscountSalTypesController::class, 'index'])->middleware('permission:view_discount_types|view_discounts|view_employees');
        Route::post('discount-sal-types', [DiscountSalTypesController::class, 'store'])->middleware('permission:create_discount_types');
        Route::put('discount-sal-types/{id}', [DiscountSalTypesController::class, 'update'])->middleware('permission:edit_discount_types');
        Route::delete('discount-sal-types/{id}', [DiscountSalTypesController::class, 'destroy'])->middleware('permission:delete_discount_types');

        Route::get('allowances', [AllowancesController::class, 'index'])->middleware('permission:view_allowance_types|view_allowances|view_employees');
        Route::post('allowances', [AllowancesController::class, 'store'])->middleware('permission:create_allowance_types');
        Route::put('allowances/{id}', [AllowancesController::class, 'update'])->middleware('permission:edit_allowance_types');
        Route::delete('allowances/{id}', [AllowancesController::class, 'destroy'])->middleware('permission:delete_allowance_types');

        // Financial Operations (CRUD)
        Route::get('sanctions', [MainSalaryEmployeeSanctionsController::class, 'index'])->middleware('permission:view_sanctions');
        Route::get('sanctions/{id}', [MainSalaryEmployeeSanctionsController::class, 'show'])->middleware('permission:view_sanctions');
        Route::post('sanctions', [MainSalaryEmployeeSanctionsController::class, 'store'])->middleware('permission:create_sanctions');
        Route::put('sanctions/{id}', [MainSalaryEmployeeSanctionsController::class, 'update'])->middleware('permission:edit_sanctions');
        Route::delete('sanctions/{id}', [MainSalaryEmployeeSanctionsController::class, 'destroy'])->middleware('permission:delete_sanctions');

        Route::get('absences', [MainSalaryEmployeeAbsencesController::class, 'index'])->middleware('permission:view_absences');
        Route::get('absences/{id}', [MainSalaryEmployeeAbsencesController::class, 'show'])->middleware('permission:view_absences');
        Route::post('absences', [MainSalaryEmployeeAbsencesController::class, 'store'])->middleware('permission:create_absences');
        Route::put('absences/{id}', [MainSalaryEmployeeAbsencesController::class, 'update'])->middleware('permission:edit_absences');
        Route::delete('absences/{id}', [MainSalaryEmployeeAbsencesController::class, 'destroy'])->middleware('permission:delete_absences');

        Route::get('discounts', [MainSalaryEmployeeDiscountsController::class, 'index'])->middleware('permission:view_discounts');
        Route::get('discounts/{id}', [MainSalaryEmployeeDiscountsController::class, 'show'])->middleware('permission:view_discounts');
        Route::post('discounts', [MainSalaryEmployeeDiscountsController::class, 'store'])->middleware('permission:create_discounts');
        Route::put('discounts/{id}', [MainSalaryEmployeeDiscountsController::class, 'update'])->middleware('permission:edit_discounts');
        Route::delete('discounts/{id}', [MainSalaryEmployeeDiscountsController::class, 'destroy'])->middleware('permission:delete_discounts');

        Route::get('loans', [MainSalaryEmployeeLoansController::class, 'index'])->middleware('permission:view_loans');
        Route::get('loans/{id}', [MainSalaryEmployeeLoansController::class, 'show'])->middleware('permission:view_loans');
        Route::post('loans', [MainSalaryEmployeeLoansController::class, 'store'])->middleware('permission:create_loans');
        Route::put('loans/{id}', [MainSalaryEmployeeLoansController::class, 'update'])->middleware('permission:edit_loans');
        Route::delete('loans/{id}', [MainSalaryEmployeeLoansController::class, 'destroy'])->middleware('permission:delete_loans');

        Route::get('permanent-loans', [MainSalaryEmployeePermanentLoansController::class, 'index'])->middleware('permission:view_permanent_loans');
        Route::get('permanent-loans/{id}', [MainSalaryEmployeePermanentLoansController::class, 'show'])->middleware('permission:view_permanent_loans');
        Route::post('permanent-loans', [MainSalaryEmployeePermanentLoansController::class, 'store'])->middleware('permission:create_permanent_loans');
        Route::put('permanent-loans/{id}', [MainSalaryEmployeePermanentLoansController::class, 'update'])->middleware('permission:edit_permanent_loans');
        Route::delete('permanent-loans/{id}', [MainSalaryEmployeePermanentLoansController::class, 'destroy'])->middleware('permission:delete_permanent_loans');
        Route::post('permanent-loans/{id}/disburse', [MainSalaryEmployeePermanentLoansController::class, 'disburse'])->middleware('permission:disburse_permanent_loans');
        Route::get('permanent-loans/{id}/installments', [MainSalaryEmployeePermanentLoansController::class, 'installments'])->middleware('permission:view_permanent_loans');
        Route::post('permanent-loans/installments/{installment_id}/pay-cash', [MainSalaryEmployeePermanentLoansController::class, 'payCash'])->middleware('permission:pay_permanent_loans');

        Route::get('additions', [MainSalaryEmployeeAdditionsController::class, 'index'])->middleware('permission:view_additions');
        Route::get('additions/{id}', [MainSalaryEmployeeAdditionsController::class, 'show'])->middleware('permission:view_additions');
        Route::post('additions', [MainSalaryEmployeeAdditionsController::class, 'store'])->middleware('permission:create_additions');
        Route::put('additions/{id}', [MainSalaryEmployeeAdditionsController::class, 'update'])->middleware('permission:edit_additions');
        Route::delete('additions/{id}', [MainSalaryEmployeeAdditionsController::class, 'destroy'])->middleware('permission:delete_additions');

        Route::get('rewards', [MainSalaryEmployeeRewardsController::class, 'index'])->middleware('permission:view_rewards');
        Route::get('rewards/{id}', [MainSalaryEmployeeRewardsController::class, 'show'])->middleware('permission:view_rewards');
        Route::post('rewards', [MainSalaryEmployeeRewardsController::class, 'store'])->middleware('permission:create_rewards');
        Route::put('rewards/{id}', [MainSalaryEmployeeRewardsController::class, 'update'])->middleware('permission:edit_rewards');
        Route::delete('rewards/{id}', [MainSalaryEmployeeRewardsController::class, 'destroy'])->middleware('permission:delete_rewards');

        Route::get('employee-allowances', [MainSalaryEmployeeAllowancesController::class, 'index'])->middleware('permission:view_allowances');
        Route::get('employee-allowances/{id}', [MainSalaryEmployeeAllowancesController::class, 'show'])->middleware('permission:view_allowances');
        Route::post('employee-allowances', [MainSalaryEmployeeAllowancesController::class, 'store'])->middleware('permission:create_allowances');
        Route::put('employee-allowances/{id}', [MainSalaryEmployeeAllowancesController::class, 'update'])->middleware('permission:edit_allowances');
        Route::delete('employee-allowances/{id}', [MainSalaryEmployeeAllowancesController::class, 'destroy'])->middleware('permission:delete_allowances');

        // Salary Records (Months Lifecycle)
        Route::get('salary-records', [SalaryRecordsController::class, 'index'])->middleware('permission:view_salaries');
        Route::get('salary-records/pasma-dates/{id}', [SalaryRecordsController::class, 'getPasmaDates'])->middleware('permission:view_salaries');
        Route::post('salary-records/open-month/{id}', [SalaryRecordsController::class, 'doOpenMonth'])->middleware('permission:create_salaries');
        Route::post('salary-records/close-month/{id}', [SalaryRecordsController::class, 'doCloseMonth'])->middleware('permission:archive_salaries');

        // Employee Salaries
        Route::get('employee-salaries/months', [MainSalaryEmployeeController::class, 'index'])->middleware('permission:view_salaries');
        Route::get('employee-salaries/{id}', [MainSalaryEmployeeController::class, 'show'])->middleware('permission:view_salaries');
        Route::post('employee-salaries', [MainSalaryEmployeeController::class, 'store'])->middleware('permission:create_salaries');
        Route::delete('employee-salaries/{id}', [MainSalaryEmployeeController::class, 'destroy'])->middleware('permission:delete_salaries');
        Route::post('employee-salaries/{id}/archive', [MainSalaryEmployeeController::class, 'archiveSalary'])->middleware('permission:archive_salaries');
        Route::post('employee-salaries/{id}/stop', [MainSalaryEmployeeController::class, 'stopSalary'])->middleware('permission:edit_salaries');
        Route::post('employee-salaries/{id}/resume', [MainSalaryEmployeeController::class, 'resumeSalary'])->middleware('permission:edit_salaries');
        Route::get('employee-salaries/{id}/details', [MainSalaryEmployeeController::class, 'salaryDetails'])->middleware('permission:view_salary_slips|view_salaries|view_own_salary');
        Route::get('employee-salaries/{id}/print-all-details', [MainSalaryEmployeeController::class, 'printAllDetails'])->middleware('permission:view_salary_slips|view_salaries');
    });
});

// ==========================================
// Super Admin Routes (SaaS Platform Management)
// ==========================================
Route::prefix('super-admin')->middleware(['auth:sanctum'])->group(function() {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index']);
    Route::apiResource('/companies', SuperAdminCompanyController::class);

    // SaaS Plans & Subscriptions
    Route::apiResource('/plans', SuperAdminPlanController::class);
    Route::post('/plans/{id}/features', [SuperAdminPlanController::class, 'updatePlanFeatures']);
    Route::apiResource('/features', SuperAdminFeatureController::class);

    // Company Subscription & Details
    Route::get('/companies/{id}/details', [SuperAdminCompanySubscriptionController::class, 'show']);
    Route::post('/companies/{id}/subscriptions', [SuperAdminCompanySubscriptionController::class, 'storeSubscription']);
    Route::put('/subscriptions/{id}/status', [SuperAdminCompanySubscriptionController::class, 'updateSubscriptionStatus']);

    // SaaS Subscriptions & Upgrade Requests Management
    Route::get('/subscription-requests', [SuperAdminSubscriptionUpgradeRequestController::class, 'index']);
    Route::post('/subscription-requests/{id}/approve', [SuperAdminSubscriptionUpgradeRequestController::class, 'approve']);
    Route::post('/subscription-requests/{id}/reject', [SuperAdminSubscriptionUpgradeRequestController::class, 'reject']);
    Route::get('/company-subscriptions-list', [SuperAdminSubscriptionUpgradeRequestController::class, 'companySubscriptionsList']);
});
