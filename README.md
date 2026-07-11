# کلینیک زیبایی — سیستم رزرو نوبت (بک‌اند)

بک‌اند لاراول برای سیستم رزرو آنلاین نوبت کلینیک زیبایی. شامل مدیریت چندشعبه‌ای، رزرو نوبت با تشخیص تداخل زمانی، پرداخت آنلاین (زرین‌پال)، اطلاع‌رسانی ایمیل/پیامک، و پنل‌های جدای مشتری/متخصص/ادمین.

## فناوری‌ها

| بخش | فناوری |
|---|---|
| فریم‌ورک | Laravel 12 |
| احراز هویت API | Laravel Passport ^13 (Password Grant + Personal Access Tokens) |
| دیتابیس | MySQL (تست‌ها: SQLite در حافظه) |
| پرداخت | زرین‌پال (Sandbox/Production) |
| پیامک | کاوه‌نگار (Verify Lookup، REST مستقیم) |
| ایمیل | SMTP (توسعه: Mailpit) |
| تست | PHPUnit (Feature + Unit) |

## پیش‌نیازها

- PHP >= 8.2 با افزونه‌های: `pdo_mysql`, `pdo_sqlite` (برای تست), `openssl`, `mbstring`
- Composer
- MySQL >= 8
- Node.js (فقط اگه بخوای asset های خودِ لاراول رو build کنی؛ فرانت‌اند این پروژه جدا و با Next.js هست)

## نصب

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### تنظیم `.env`

```env
DB_CONNECTION=mysql
DB_DATABASE=clinic_booking
DB_USERNAME=root
DB_PASSWORD=

# Passport (بعد از php artisan passport:client --password پر کن)
PASSPORT_PASSWORD_CLIENT_ID=
PASSPORT_PASSWORD_CLIENT_SECRET=

# زرین‌پال
ZARINPAL_MERCHANT_ID=
ZARINPAL_SANDBOX=true
ZARINPAL_CALLBACK_URL=http://localhost:3000/payment/callback

# کاوه‌نگار
KAVENEGAR_API_KEY=

# ایمیل (توسعه: Mailpit)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025

# آدرس فرانت‌اند (برای لینک‌های ایمیل)
FRONTEND_URL=http://localhost:3000

# منطقه‌ی زمانی — حتماً باید تهران باشه، وگرنه ساعت نوبت‌ها اشتباه نمایش داده می‌شه
APP_TIMEZONE=Asia/Tehran
```

### migrate و seed

```bash
php artisan migrate:fresh --seed
```

Seeder دو شعبه، دو ادمین، دو مشتری، چهار دسته‌بندی، هشت خدمت، و چهار متخصص (با ساعات کاری متفاوت) می‌سازه. همه‌ی پسوردها `password123` هستن — لیست کامل حساب‌ها بعد از اجرای seeder توی ترمینال چاپ می‌شه.

### ساخت Passport Client

```bash
php artisan passport:client --password
```
Client ID و Secret رو توی `.env` بذار، بعد:
```bash
php artisan config:clear
```

⚠️ **این پروژه از Passport v13 استفاده می‌کنه که Password Grant پیش‌فرض غیرفعاله.** حتماً مطمئن شو `Passport::enablePasswordGrant()` توی `AppServiceProvider::boot()` صدا زده شده (از قبل توی این پروژه هست).

## اجرای سرور توسعه

```bash
$env:PHP_CLI_SERVER_WORKERS=4   # PowerShell — یا export روی لینوکس/مک
php artisan serve --no-reload
```

⚠️ **چرا `PHP_CLI_SERVER_WORKERS=4` لازمه؟** چون فلوی لاگین (`/api/login`, `/api/verify-otp`) خودش یه درخواست HTTP داخلی به `/oauth/token` می‌زنه (Password Grant). سرور توسعه‌ی پیش‌فرض PHP تک‌رشته‌ایه، پس این خودارجاعی باعث قفل‌شدن (deadlock) می‌شه مگه چند worker داشته باشه.

برای production، از یه وب‌سرور واقعی (Nginx + PHP-FPM) استفاده کن، نه `php artisan serve`.

## کرون‌جاب (لغو خودکار نوبت پرداخت‌نشده)

```bash
php artisan schedule:work
```
(یا crontab واقعی روی production که هر دقیقه `php artisan schedule:run` رو صدا بزنه)

## اجرای تست‌ها

```bash
php artisan test
```
از یه دیتابیس SQLite جدا (در حافظه) استفاده می‌کنه — کاری به دیتابیس واقعیت نداره. ~۷۰ تست، شامل: منطق ساعت‌های خالی، Auth، فلوی رزرو، تأیید پرداخت، کنترل دسترسی نقش‌ها، OTP، CRUD ادمین، پنل متخصص، Refund.

## ساختار نقش‌ها

| نقش | دسترسی |
|---|---|
| `customer` | رزرو/لغو/نظر روی نوبت‌های خودش |
| `specialist` | مدیریت نوبت‌های خودش، ساعات کاری، مرخصی |
| `admin` | مدیریت کامل: شعبه، خدمت، متخصص، کاربران، نظرات، ریفاند، داشبورد آماری |

جزئیات کامل endpoint ها توی `API_DOCUMENTATION.md` هست.

## نکات مهم معماری

- **پرداخت**: هر نوبت `pending_payment` می‌شه، بعد پرداخت زرین‌پال تأیید بشه `confirmed` می‌شه. اگه ظرف ۱۵ دقیقه پرداخت نشه، Job خودکار لغوش می‌کنه.
- **Refund**: نیمه‌خودکاره — سیستم فقط پرداخت رو `refund_pending` علامت می‌زنه؛ ادمین باید دستی از پنل زرین‌پال ریفاند کنه و بعد توی سیستم تأیید کنه (چون API عمومی زرین‌پال ریفاند خودکار نداره).
- **OTP**: لاگین با موبایل، پسورد واقعی کاربر رو دست‌کاری نمی‌کنه (یه ستون جدا `otp_password` داره).
- **تداخل زمانی**: با `lockForUpdate()` هنگام ثبت نهایی نوبت چک می‌شه (نه فقط موقع نمایش لیست ساعت‌های خالی) تا race condition پیش نیاد.