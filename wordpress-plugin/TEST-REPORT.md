# گزارش تست کامل — افزونه «کانفیگوراتور سرور فالنیک»

تاریخ: ۲۰۲۶-۰۹-۰۷ · محیط: PHP واقعی 8.2.29 (php-wasm) + شبیه‌ساز وردپرس/wpdb · فایل‌های عینی افزونه اجرا می‌شوند

## نتیجه نهایی: ✅ ۵۲۳ تست پاس — ۰ خطا (+ لینت PHP ۱۵/۱۵)

| سوئیت | پوشش | نتیجه |
|---|---|---|
| test-bootstrap-file | فایل اصلی افزونه: فعال‌سازی روی دیتابیس خالی (۱۳ جدول + seed ۷۱/۱۸۹)، سیم‌کشی init (public/AJAX/admin)، شورت‌کد، روتین ارتقا، لینک‌های سریع | ۲۴/۰ |
| test-install | ساخت/تأیید/ترمیم شِما؛ ترتیب ستون‌ها؛ idempotency؛ ترمیم جدول/ستون/ایندکس/AUTO_INCREMENT مفقود؛ verify() سالم برای ۱۳ جدول | ۵۱/۰ |
| **test-schema-parity** | **تطابق کامل فیلد‌به‌فیلد با دامپ اصلی: نام و ترتیب و تعریف کامل تک‌تک ستون‌ها (نوع/NULL/DEFAULT/AUTO_INCREMENT/CHECK) + ایندکس‌ها + PRIMARY KEY هر ۱۳ جدول؛ شمارش INSERT های دامپ == ردیف‌های نصب‌شده؛ پوشش ادیتور روی همه ستون‌های قابل‌ویرایش** | **۹۵/۰** |
| test-ajax | ۴ اندپوینت: گیت nonce، get_data (لیست/شاسی + کش + TTL=0)، recommend، submit (اعتبارسنجی کامل، توان/قیمت سمت سرور، کد پیگیری یکتا، ریت‌لیمیت ۴۲۹)، ai_chat (پاسخ تمیز، ۵۰۲، عدم لو رفتن کلید) | ۴۶/۰ |
| test-functions | ادغام تنظیمات، rate-limiter، falnic_mb_truncate، اعتبارسنجی JSON، پاکسازی کش | ۲۵/۰ |
| test-crud | لیست/جستجو(XSS)/مرتب‌سازی/صفحه‌بندی؛ فرم؛ sanitize (int/float/json/الزامی)؛ درج/ویرایش/تکراری/حذف/حذف گروهی؛ purge بعد از نوشتن؛ گیت دسترسی | ۴۸/۰ |
| test-admin | منوها، asset فقط در صفحات افزونه، اعلان یک‌بارمصرف، ابزارها (repair/seed/purge)، تغییر وضعیت/حذف درخواست، sanitize تنظیمات | ۳۹/۰ |
| **test-admin-screens** | **گشت کامل پنل: هر ۱۲ صفحه CRUD — لیست (شمارش دقیق ردیف‌ها: ۳/۷۱/۲۸/۱۸۹/۲۷/۲۱/۴۷/۱۹/۲۰/۳/۱۷/۳) + فرم جدید (همه فیلدهای هر جدول موجود) + فرم ویرایش ردیف ۱ + جستجو** | **۱۴۴/۰** |
| test-shortcode | asset شرطی فقط هنگام رندر، FALNIC_SC_CONFIG (۶ کلید)، ریشه `#falnic-sc-app` dir=rtl، گارد دوباره، aiEnabled | ۱۷/۰ |
| **test-frontend-parity** | **تطابق فرانت با نسخه قبل: هر ۱۱۰ id صفحهٔ index.php اصلی در app-shell موجود (پورت ۱:۱)؛ هر ۸۹ ارجاع id در main.js به المان واقعی می‌رسد؛ security.js helpers + ۵۴ نقطه escape؛ ۴ اکشن AJAX منطبق؛ بدون هیچ URL خارجی/CDN؛ فونت محلی** | **۱۹/۰** |
| test-uninstall | حذف optionها + transientها با حفظ خارجی‌ها؛ drop جدول‌ها فقط با opt-in؛ بدون تنظیمات → داده محفوظ | ۱۵/۰ |

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
