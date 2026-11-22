# دليل تشغيل Laravel Reverb والمهام المجدولة في الإنتاج (Production Daemon)

يحتوي هذا المجلد على ملفات الإعداد الجاهزة لتشغيل خادم الإشعارات الفورية (**Laravel Reverb**) وطوابير المهام (**Queue Worker**) والمجدول (**Scheduler**) بشكل مستمر وتلقائي في بيئة الإنتاج.

---

## أولاً: إعداد متغيرات البيئة (`.env`) للإنتاج

### 1. في الخادم الخلفي (`backend/.env`):
```env
APP_ENV=production
APP_DEBUG=false
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=bjn_hrms_app_id
REVERB_APP_KEY=bjn_hrms_app_key
REVERB_APP_SECRET=ضع_مفتاحاً_سرياً_قوياً_هنا
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_HOST=api.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS="https://yourdomain.com,https://www.yourdomain.com"
```

### 2. في الواجهة الأمامية (`frontend/.env`):
```env
NEXT_PUBLIC_REVERB_APP_KEY=bjn_hrms_app_key
NEXT_PUBLIC_REVERB_HOST=api.yourdomain.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

---

## ثانياً: التشغيل على سيرفر Linux (Ubuntu / Debian) — الموصى به للإنتاج

### 1. تثبيت وتفعيل Supervisor (لتشغيل Reverb و Queue كـ Daemon):
```bash
sudo apt update && sudo apt install -y supervisor

# نسخ ملفات الإعداد (عدّل المسار /var/www/bjn_hrms حسب مسار مشروعك على السيرفر)
sudo cp deploy/supervisor/bjn-hrms-reverb.conf /etc/supervisor/conf.d/
sudo cp deploy/supervisor/bjn-hrms-worker.conf /etc/supervisor/conf.d/

# إعادة تحميل Supervisor وتشغيل الخدمات
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bjn-hrms-reverb
sudo supervisorctl start bjn-hrms-worker:*

# التحقق من الحالة
sudo supervisorctl status
```

### 2. إعداد Nginx Reverse Proxy لاتصالات `wss://`:
قم بتضمين محتوى الملف `deploy/nginx/reverb-proxy.conf` داخل بلوك `server { ... }` الخاص بشهادة SSL (منفذ 443)، ثم أعد تحميل Nginx:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 3. تفعيل المجدول الدوري (Laravel Scheduler Cron):
لتشغيل فحص انتهاء الاشتراكات (`subscriptions:check-expirations`) تلقائياً كل ساعة:
```bash
crontab -e
```
وأضف السطر التالي:
```cron
* * * * * cd /var/www/bjn_hrms/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 4. إعادة تشغيل Reverb بعد كل تحديث للكود (Deployment):
```bash
cd /var/www/bjn_hrms/backend
php artisan config:cache
php artisan reverb:restart
php artisan queue:restart
```

---

## ثالثاً: التشغيل على سيرفر Windows (Windows Server / XAMPP)

### الخيار أ: التشغيل السريع مع إعادة التشغيل التلقائي
شغّل السكربتين التاليين من مجلد `backend/deploy/windows/`:
- `start-reverb.bat` — لتشغيل خادم Reverb على المنفذ `8080` مع إعادة تشغيل تلقائية عند أي توقف.
- `start-scheduler.bat` — لتشغيل المجدول (`schedule:work`) في الخلفية.

### الخيار ب: التثبيت كخدمة Windows Service دائمة عبر NSSM (موصى به لـ Windows Server)
باستخدام أداة [NSSM](https://nssm.cc/download) كمسؤول (Administrator):
```cmd
nssm install BjnHrmsReverb "C:\xampp\php\php.exe" "C:\xampp\htdocs\bjn_hrms\backend\artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction"
nssm set BjnHrmsReverb AppDirectory "C:\xampp\htdocs\bjn_hrms\backend"
nssm set BjnHrmsReverb AppStdout "C:\xampp\htdocs\bjn_hrms\backend\storage\logs\reverb.log"
nssm set BjnHrmsReverb AppStderr "C:\xampp\htdocs\bjn_hrms\backend\storage\logs\reverb-error.log"
nssm start BjnHrmsReverb
```
وبالمثل لمجدول المهام:
```cmd
nssm install BjnHrmsScheduler "C:\xampp\php\php.exe" "C:\xampp\htdocs\bjn_hrms\backend\artisan schedule:work --no-interaction"
nssm set BjnHrmsScheduler AppDirectory "C:\xampp\htdocs\bjn_hrms\backend"
nssm start BjnHrmsScheduler
```
