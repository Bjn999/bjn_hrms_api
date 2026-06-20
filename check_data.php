<?php \ = new mysqli('127.0.0.1', 'root', '', 'hrms'); \ = \->query('SELECT * FROM employee_salary_archive LIMIT 10'); while(\=\->fetch_assoc()) print_r(\);
