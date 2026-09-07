# گزارش تست کامل — افزونه «کانفیگوراتور سرور فالنیک»

تاریخ: ۲۰۲۶-۰۹-۰۷ (به‌روزرسانی پس از فیکس خطای نصب واقعی) · محیط: PHP واقعی 8.2.29 (php-wasm) + شبیه‌ساز وردپرس/wpdb · فایل‌های عینی افزونه اجرا می‌شوند

## نتیجه نهایی: ✅ ۵۵۳ تست پاس — ۰ خطا (+ لینت PHP ۱۵/۱۵)

| سوئیت | پوشش | نتیجه |
|---|---|---|
| **test-realflow-admin** | **جریان لود واقعی wp-admin (رگرسیون خطای staging): فقط فایل اصلی افزونه لود می‌شود → init → admin_menu → رندر هر ۱۶ صفحه از طریق هوک‌های واقعی → admin_notices/admin_init/admin_enqueue_scripts** | **۱۳/۰** |
| **test-realflow-frontend** | جریان واقعی فرانت: init → wp_enqueue_scripts → شورت‌کد؛ کلاس ادمین روی فرانت لود نمی‌شود | ۷/۰ |
| **test-realflow-ajax** | جریان واقعی admin-ajax: DOING_AJAX → init → فراخوانی اندپوینت‌ها از رجیستری هوک (priv + nopriv) | ۱۰/۰ |
| test-bootstrap-file | فایل اصلی: فعال‌سازی روی DB خالی (۱۳ جدول + seed)، سیم‌کشی، روتین ارتقا، لینک‌ها | ۲۴/۰ |
| test-install | ساخت/تأیید/ترمیم شِما؛ ترتیب ستون‌ها؛ idempotency؛ ترمیم جدول/ستون/ایندکس/AUTO_INCREMENT؛ verify() | ۵۱/۰ |
| test-schema-parity | تطابق فیلد‌به‌فیلد با دامپ اصلی: نام/ترتیب/تعریف کامل تک‌تک ستون‌ها + ایندکس‌ها + PK هر ۱۳ جدول؛ شمارش INSERT == ردیف‌های نصب | ۹۵/۰ |
| test-ajax | ۴ اندپوینت: nonce، get_data (+کش)، recommend، submit (اعتبارسنجی/کد پیگیری/ریت‌لیمیت)، ai_chat | ۴۶/۰ |
| test-functions | ادغام تنظیمات، rate-limiter، mb truncate، اعتبارسنجی JSON، پاکسازی کش | ۲۵/۰ |
| test-crud | لیست/جستجو(XSS)/مرتب‌سازی/صفحه‌بندی؛ فرم؛ sanitize؛ درج/ویرایش/تکراری/حذف/گروهی؛ purge؛ گیت دسترسی | ۴۸/۰ |
| test-admin | منوها، asset شرطی، اعلان، ابزارها، وضعیت/حذف درخواست، sanitize تنظیمات | ۳۹/۰ |
| test-admin-screens | گشت کامل پنل: هر ۱۲ صفحه CRUD — لیست (شمارش دقیق) + فرم جدید (همه فیلدها) + ویرایش + جستجو | ۱۴۴/۰ |
| test-shortcode | asset شرطی فقط هنگام رندر، FALNIC_SC_CONFIG، ریشه dir=rtl، گارد دوباره، aiEnabled | ۱۷/۰ |
| test-frontend-parity | ۱۱۰ id اصلی در app-shell؛ ۸۹ ارجاع id در main.js معتبر؛ ۵۴ نقطه escape؛ بدون URL خارجی | ۱۹/۰ |
| test-uninstall | حذف optionها + transientها؛ drop جدول‌ها فقط با opt-in؛ بدون تنظیمات → داده محفوظ | ۱۵/۰ |

## باگ‌های واقعی که پیدا و رفع شدند
1. `Falnic_SC_CRUD::header_cell()` — عدم‌تطابق آرگومان printf → ArgumentCountError در صفحه لیست هر ۱۲ جدول (رفع: label و arrow دو آرگومان جدا)
2. `handle_request_status()` — whitelist حساس به بزرگی/کوچکی مقابل sanitize_key → تغییر وضعیت درخواست‌ها هرگز اعمال نمی‌شد (رفع: نگاشت lowercase→استاندارد)
3. **خطای Fatal روی سایت واقعی (staging):** «Call to undefined function falnic_sc_catalog_defs()» در `class-falnic-sc-admin.php:66` هنگام `admin_menu` — فایل `includes/falnic-sc-catalog-defs.php` در هیچ‌جای زنجیرهٔ لود افزونه require نشده بود؛ تست‌های قبلی آن را نمی‌دیدند چون در bootstrap تست، دستی قبل از بقیه لود می‌شد.
   **رفع کامل:** هر فایل حالا وابستگی‌های خودش را require می‌کند (admin → catalog-defs؛ crud → tables/functions/catalog-defs؛ ai → functions؛ install → tables/functions؛ ajax → tables/functions/…) و سه سوئیت جدید «realflow» فقط فایل اصلی افزونه را لود می‌کنند و کل چرخهٔ هوک وردپرس را شبیه‌سازی می‌کنند — این تست‌ها دقیقاً همین خطا را قبل از فیکس بازتولید می‌کنند (تأیید شد) و بعد از فیکس سبزند.

## نحوه اجرا
```bash
cd /home/user/.phplint && bash run-all.sh
```


## تطابق شِما با دامپ اصلی (جزئیات)
- ۱۳ جدول، نام‌های فیزیکی یکسان (Chassis, CPUs, RAMs, Storage_Drives, Storage_Controllers, GPUs, Network_Adapters, Risers, HBAs, Optical_Drives, Power_Supplies, Prepared_Server_Offers, User_Configurations)
- **تک‌تک ستون‌ها** (بیش از ۲۰۰ ستون): نام + ترتیب + تعریف کامل (نوع، NULL/NOT NULL، DEFAULT، AUTO_INCREMENT، CHECK json_valid) بایت‌به‌بایت یکسان؛ AUTO_INCREMENT دامپ که به‌صورت `ALTER TABLE ... MODIFY` است با تعریف inline افزونه هم‌ارزش است
- همه ایندکس‌ها (PRIMARY، uq_part_number، idx_socket_type و…) + تعاریف یکسان
- شمارش INSERT دامپ == ردیف‌های seed شده بعد از فعال‌سازی؛ جدول درخواست‌ها هرگز seed نمی‌شود
- ستون‌های `created_at/updated_at` (current_timestamp خودکار) به‌درستی از ادیتور مستثنا شده‌اند؛ بقیه همه ستون‌ها در پنل قابل ویرایش‌اند

## فرانت‌اند (پورت ۱:۱ از نسخه قبل)
- شل اپ دقیقاً همان مارک‌آپ index.php اصلی است (۱۱۰/۱۱۰ id؛ فقط رپر `falnic-sc-app` اضافه شده)
- main.js بدون تغییر رفتار روی همان مارک‌آپ کار می‌کند: ۸۹ ارجاع id همه معتبر
- قرارداد AJAX همان ۴ اکشن نسخه قبل + nonce وردپرس
- فایل‌ها کاملاً محلی: بدون CDN، فونت/لوگوی لوکال

## باگ‌های واقعی که در تست‌های قبلی پیدا و رفع شد (همچنان سالم)
1. `Falnic_SC_CRUD::header_cell()` — عدم‌تطابق آرگومان printf (رفع شده)
2. `handle_request_status()` — whitelist حساس به بزرگی/کوچکی مقابل sanitize_key (رفع شده با نگاشت lowercase→استاندارد)

## نحوه اجرا
```bash
cd /home/user/.phplint && bash run-all.sh
```
