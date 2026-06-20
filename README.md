# Bjn HRMs API - الواجهة الخلفية لنظام إدارة الموارد البشرية

المستودع البرمجي الخاص بالواجهة الخلفية (Backend API) لنظام **Bjn HRMs** لإدارة الموارد البشرية والرواتب. تم تصميم هذا النظام ليعمل بشكل منفصل كلياً كخدمة RESTful API لتغذية واجهات النظام بالبيانات والعمليات الحسابية اللازمة.

---

## 🛠️ التقنيات المستخدمة (Backend Tech Stack)

*   **Laravel Framework:** الإطار الأساسي لبناء وتطوير الـ RESTful API.
*   **Laravel Sanctum:** للمصادقة وتأمين نقاط الاتصال (Token-based Authentication).
*   **MySQL / MariaDB:** قاعدة البيانات لإدارة وتخزين جداول الموظفين، الحركات والرواتب.

---

## 🔗 مسارات الـ API الرئيسية (Core Endpoints)

تعمل جميع مسارات الإدارة تحت البادئة `api/admin/` وتتطلب مصادقة (Bearer Token) ما عدا مسار تسجيل الدخول:

### 1. المصادقة والتحقق (Authentication)
*   `POST /api/admin/login` - تسجيل دخول المدير/المشرف.
*   `GET /api/admin/user` - جلب بيانات المستخدم الحالي.
*   `POST /api/admin/logout` - تسجيل الخروج وإبطال الرمز المميز.

### 2. الإعدادات العامة والتقويم المالي
*   `GET|POST /api/admin/generalSettings` - جلب وتحديث الإعدادات العامة للوحة التحكم.
*   `GET|POST|PUT|DELETE /api/admin/finance-calendars` - إدارة التقويم المالي والسنوات والأشهر المالية.

### 3. الموارد والتعريفات الأساسية (CRUD Resources)
إدارة شاملة (إضافة، تعديل، حذف، جلب) للموارد التنظيمية التالية:
*   `/api/admin/branches` - الفروع.
*   `/api/admin/shifts` - فترات الدوام.
*   `/api/admin/departments` - الإدارات والأقسام.
*   `/api/admin/jobs-categories` - تصنيفات الوظائف.
*   `/api/admin/qualifications` - المؤهلات العلمية.
*   `/api/admin/occasions` - العطلات والمناسبات الرسمية.
*   `/api/admin/resignations` - مبررات الاستقالة.
*   `/api/admin/nationalities` - الجنسيات.
*   `/api/admin/religions` - الديانات.
*   `/api/admin/blood-groups` - فصائل الدم.
*   `/api/admin/countries` | `governorates` | `centers` - التهيئة الجغرافية ومراكز السكن.

### 4. شؤون الموظفين والملفات (Employees Affairs)
*   `GET|POST|PUT|DELETE /api/admin/employees` - إدارة بيانات الموظفين بالكامل.
*   `POST|PUT|DELETE /api/admin/employees/{id}/fixed-allowances` - البدلات الثابتة للموظف.
*   `POST|DELETE /api/admin/employees/{id}/files` - إدارة ملفات ووثائق الموظفين المرفوعة.
*   `GET /api/admin/employees/{id}/salary-archive` - جلب السجل التاريخي لأرشيف رواتب الموظف.

### 5. العمليات والعمليات المالية الشهرية
إدارة العمليات المؤثرة على الراتب الشهري:
*   `/api/admin/sanctions` - الجزاءات والعقوبات الإدارية.
*   `/api/admin/absences` - الغيابات وأيام عدم الحضور.
*   `/api/admin/discounts` - الخصومات المباشرة.
*   `/api/admin/loans` - القروض المؤقتة.
*   `/api/admin/permanent-loans` - القروض المستديمة وجدولة الأقساط الشهرية.
*   `/api/admin/additions` - الإضافات المالية والمستحقات الطارئة.
*   `/api/admin/rewards` - المكافآت المالية.

### 6. احتساب وصرف الرواتب (Salary Lifecycle)
*   `GET /api/admin/salary-records` - إدارة حركات الرواتب الشهرية ودورة الشهر المالي.
*   `POST /api/admin/salary-records/open-month/{id}` - فتح الشهر المالي.
*   `POST /api/admin/salary-records/close-month/{id}` - إغلاق الشهر المالي تمهيداً للصرف النهائي.
*   `POST /api/admin/employee-salaries` - احتساب وصرف راتب موظف لشهر محدد.
*   `POST /api/admin/employee-salaries/{id}/archive` - أرشفة الرواتب المحتسبة.
*   `POST /api/admin/employee-salaries/{id}/stop|resume` - إيقاف أو استئناف راتب الموظف مؤقتاً.

---

## 🚀 تشغيل خادم الـ API محلياً

### 1. المتطلبات الأساسية
تأكد من تثبيت بيئة **PHP (الإصدار 8.1 أو أحدث)** ومثبت الحزم **Composer**، بالإضافة لخادم قواعد بيانات (مثل MySQL عبر XAMPP).

### 2. إعداد ملف التكوين وقاعدة البيانات
1. انسخ ملف الإعدادات:
   ```bash
   cp .env.example .env
   ```
2. قم بإنشاء قاعدة بيانات جديدة باسم مناسب في خادم MySQL (مثال: `bjn_hrms`).
3. عدّل إعدادات الاتصال بقاعدة البيانات في ملف `.env`:
   ```env
   DB_DATABASE=bjn_hrms
   DB_USERNAME=root
   DB_PASSWORD=
   ```

### 3. تثبيت الاعتماديات وتوليد مفتاح التشفير
```bash
composer install
php artisan key:generate
```

### 4. تشغيل الهجرة والتهيئة (Migrations & Seeding)
لتجهيز جداول قاعدة البيانات والبيانات الأساسية الافتراضية:
```bash
php artisan migrate --seed
```

### 5. تشغيل خادم التطوير المحلي
```bash
php artisan serve
```
سيكون خادم الـ API متاحاً بشكل افتراضي على الرابط: `http://127.0.0.1:8000`.
