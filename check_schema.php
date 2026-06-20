<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

print_r(Illuminate\Support\Facades\Schema::getColumnListing('main_salary_employee_discounts'));
echo "\n====\n";
print_r(Illuminate\Support\Facades\Schema::getColumnListing('main_salary_employee_loans'));
