<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\AdminPanelSettingsController;
use App\Http\Controllers\Api\Admin\FinanceCalendersController;
use App\Http\Controllers\Api\Admin\BranchesController;
use App\Http\Controllers\Api\Admin\ShiftsController;
use App\Http\Controllers\Api\Admin\DepartmentsController;
use App\Http\Controllers\Api\Admin\JobsCategoriesController;
use App\Http\Controllers\Api\Admin\QualificationsController;
use App\Http\Controllers\Api\Admin\OccasionsController;
use App\Http\Controllers\Api\Admin\ResignationsController;
use App\Http\Controllers\Api\Admin\NationalitiesController;
use App\Http\Controllers\Api\Admin\SalaryRecordsController;
use App\Http\Controllers\Api\Admin\ReligionsController;
use App\Http\Controllers\Api\Admin\BloodGroupsController;
use App\Http\Controllers\Api\Admin\CountriesController;
use App\Http\Controllers\Api\Admin\GovernoratesController;
use App\Http\Controllers\Api\Admin\CentersController;
use App\Http\Controllers\Api\Admin\EmployeesController;
use App\Http\Controllers\Api\Admin\AdditionalSalTypesController;
use App\Http\Controllers\Api\Admin\DiscountSalTypesController;
use App\Http\Controllers\Api\Admin\AllowancesController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeSanctionsController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeAbsencesController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeDiscountsController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeLoansController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeePermanentLoansController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeAdditionsController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeRewardsController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeAllowancesController;
use App\Http\Controllers\Api\Admin\MainSalaryEmployeeController;

Route::prefix('admin')->group(function() {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // Settings
        Route::get('/generalSettings', [AdminPanelSettingsController::class, 'index']);
        Route::post('/generalSettings', [AdminPanelSettingsController::class, 'update']);

        // Finance Calendars
        Route::get('/finance-calendars', [FinanceCalendersController::class, 'index']);
        Route::post('/finance-calendars', [FinanceCalendersController::class, 'store']);
        Route::put('/finance-calendars/{id}', [FinanceCalendersController::class, 'update']);
        Route::delete('/finance-calendars/{id}', [FinanceCalendersController::class, 'destroy']);
        Route::post('/finance-calendars/{id}/open', [FinanceCalendersController::class, 'open']);
        Route::get('/finance-calendars/{id}/months', [FinanceCalendersController::class, 'months']);

        // Resources
        Route::apiResource('branches', BranchesController::class)->except(['show']);
        Route::apiResource('shifts', ShiftsController::class)->except(['show']);
        Route::apiResource('departments', DepartmentsController::class)->except(['show']);
        Route::apiResource('jobs-categories', JobsCategoriesController::class)->except(['show']);
        Route::apiResource('qualifications', QualificationsController::class)->except(['show']);
        Route::apiResource('occasions', OccasionsController::class)->except(['show']);
        Route::apiResource('resignations', ResignationsController::class)->except(['show']);
        Route::apiResource('nationalities', NationalitiesController::class)->except(['show']);
        Route::apiResource('religions', ReligionsController::class)->except(['show']);
        Route::apiResource('blood-groups', BloodGroupsController::class)->except(['show']);
        Route::apiResource('countries', CountriesController::class)->except(['show']);
        Route::apiResource('governorates', GovernoratesController::class)->except(['show']);
        Route::apiResource('centers', CentersController::class)->except(['show']);
        
        // Employees Affairs
        Route::get('employees/required-data', [EmployeesController::class, 'get_required_data']);
        Route::post('employees/{id}/fixed-allowances', [EmployeesController::class, 'addFixedAllowance']);
        Route::put('employees/fixed-allowances/{id}', [EmployeesController::class, 'updateFixedAllowance']);
        Route::delete('employees/fixed-allowances/{id}', [EmployeesController::class, 'deleteFixedAllowance']);
        Route::post('employees/{id}/files', [EmployeesController::class, 'addFile']);
        Route::delete('employees/files/{id}', [EmployeesController::class, 'deleteFile']);
        Route::get('employees/{id}/salary-archive', [EmployeesController::class, 'getSalaryArchive']);
        Route::apiResource('employees', EmployeesController::class);
        Route::apiResource('additional-sal-types', AdditionalSalTypesController::class)->except(['show']);
        Route::apiResource('discount-sal-types', DiscountSalTypesController::class)->except(['show']);
        Route::apiResource('allowances', AllowancesController::class)->except(['show']);
        Route::apiResource('sanctions', MainSalaryEmployeeSanctionsController::class);
        Route::apiResource('absences', MainSalaryEmployeeAbsencesController::class);
        Route::apiResource('discounts', MainSalaryEmployeeDiscountsController::class);
        Route::apiResource('loans', MainSalaryEmployeeLoansController::class);
        Route::apiResource('permanent-loans', MainSalaryEmployeePermanentLoansController::class);
        Route::apiResource('additions', MainSalaryEmployeeAdditionsController::class);
        Route::apiResource('rewards', MainSalaryEmployeeRewardsController::class);
        Route::apiResource('employee-allowances', MainSalaryEmployeeAllowancesController::class);
        Route::post('permanent-loans/{id}/dismiss', [MainSalaryEmployeePermanentLoansController::class, 'dismiss']);
        Route::get('permanent-loans/{id}/installments', [MainSalaryEmployeePermanentLoansController::class, 'installments']);
        // Salary Records (Months Lifecycle)
        Route::get('salary-records', [SalaryRecordsController::class, 'index']);
        Route::get('salary-records/pasma-dates/{id}', [SalaryRecordsController::class, 'getPasmaDates']);
        Route::post('salary-records/open-month/{id}', [SalaryRecordsController::class, 'doOpenMonth']);
        Route::post('salary-records/close-month/{id}', [SalaryRecordsController::class, 'doCloseMonth']);

        // Employee Salaries
        Route::get('employee-salaries/months', [MainSalaryEmployeeController::class, 'index']);
        Route::get('employee-salaries/{id}', [MainSalaryEmployeeController::class, 'show']);
        Route::post('employee-salaries', [MainSalaryEmployeeController::class, 'store']);
        Route::delete('employee-salaries/{id}', [MainSalaryEmployeeController::class, 'destroy']);
        Route::post('employee-salaries/{id}/archive', [MainSalaryEmployeeController::class, 'archiveSalary']);
        Route::post('employee-salaries/{id}/stop', [MainSalaryEmployeeController::class, 'stopSalary']);
        Route::post('employee-salaries/{id}/resume', [MainSalaryEmployeeController::class, 'resumeSalary']);
        Route::get('employee-salaries/{id}/details', [MainSalaryEmployeeController::class, 'salaryDetails']);
        Route::get('governorates-by-country/{country_id}', [CentersController::class, 'getGovernorates']);
    });
});
