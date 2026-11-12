# تثبيت وتحديث عصب سوفت — الإصدار V2

## متطلبات التشغيل
- PHP 8.2 أو أحدث مع pdo_mysql وmbstring وfileinfo وopenssl.
- Composer 2 وMySQL 8 أو MariaDB متوافقة.
- لا تحتاج إلى npm لأن CSS/JavaScript ملفات محلية جاهزة.

## 1. إذا كان الإصدار السابق موجوداً (مهم: لا تحذف قاعدة البيانات)

1. خذ نسخة احتياطية من قاعدة بيانات `asab_realty` وملف `.env` ومجلد `storage/app/public` إن كان يحوي ملفات.
2. أوقف `php artisan serve` بواسطة Ctrl+C.
3. استخرج ZIP الجديد في مجلد **جديد**، ثم انسخ ملف `.env` القديم إليه. لا تنسخ مجلد `vendor`، بل أعد تثبيته أو انقله فقط إذا كان متوافقاً.
4. شغّل الأوامر من جذر المشروع الجديد:

```cmd
composer install --prefer-dist
php artisan config:clear
php artisan migrate
php artisan storage:link
php artisan optimize:clear
php artisan serve
```

5. افتح `http://127.0.0.1:8000/` لواجهة العقارات العامة.
6. افتح `http://127.0.0.1:8000/login` للدخول بحساب مالك المنصة المعتاد.
7. من لوحة `/platform` أنشئ شركة (إذا لم تنشئ واحدة سابقاً)، أو اضغط **فتح مساحة الشركة**.
8. من القائمة **العقارات والمباني ← إضافة عقار**، ثم **الوحدات العقارية ← إضافة وحدة**، ثم **إعلانات العقارات والموقع** لعرض الوحدة ونشر العقار.

**لا تستخدم أبداً `php artisan migrate:fresh` على قاعدة بيانات بها معلومات تريد الاحتفاظ بها.**

## 2. تثبيت جديد

```cmd
copy .env.example .env
```

عدّل `.env` لوضع بيانات MySQL (`DB_DATABASE=asab_realty`) وبريد وكلمة مرور مدير قوية (12 حرفاً أو أكثر):

```dotenv
PLATFORM_OWNER_NAME="ASAB Platform Owner"
PLATFORM_OWNER_EMAIL="your-real-email@example.com"
PLATFORM_OWNER_PASSWORD="SET_AN_UNIQUE_PRIVATE_PASSWORD_12_PLUS"
DEMO_DATA=false
```

ثم نفّذ:

```cmd
composer install --prefer-dist
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

الأوامر في `.env` هي **سطور إعدادات داخل الملف** وليست أوامر CMD.

## 3. بيانات عرض خيالية (اختيارية)

فعّل `DEMO_DATA=true` في `.env` على قاعدة تطوير محلية فقط، ثم:

```cmd
php artisan config:clear
php artisan db:seed
```

ستظهر عقارات تجريبية خيالية منشورة. لا تفعّل هذا الخيار على بيانات عميل حقيقي.

## 4. تشغيل مشروعك القديم أم الجديد؟

الرابط `/` أصبح **واجهة العقارات العامة**. لوحة المالك ` /platform `، لوحة تشغيل الشركة `/dashboard`، إدارة العقارات `/manage/properties`، إدارة الإعلانات `/listings`، صفحة البحث `/properties`.

إذا كانت صور العقارات لا تظهر، شغّل `php artisan storage:link` وتأكد من صلاحيات التخزين. إذا فقدت كلمة مرور المدير فلا تضعها في رسالة دردشة أو مستودع GitHub.

## 5. اختبارات بعد التثبيت

```cmd
php artisan route:list
php artisan test
```

تحتاج اختبارات SQLite in-memory إلى امتداد `pdo_sqlite`. اختبر MySQL الفعلي محلياً أيضاً قبل النشر.
