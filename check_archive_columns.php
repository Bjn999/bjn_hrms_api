<?php
$c = new mysqli('127.0.0.1', 'root', '', 'hrms');
$r = $c->query('SHOW COLUMNS FROM employee_salary_archive');
while ($row = $r->fetch_assoc()) {
    print_r($row);
}
