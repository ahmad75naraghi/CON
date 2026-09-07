# گزارش تست کامل — افزونه «کانفیگوراتور سرور فالنیک»

تاریخ: ۲۰۲۶-۰۹-۰۷ (به‌روزرسانی v1.1.0 — فیکس h() در HBA، هدرهای ثابت کانفیگوراتور، لوگوی سایت، بازطراحی پنل مدیریت) · محیط: PHP واقعی 8.2.29 (php-wasm) + شبیه‌ساز وردپرس/wpdb · فایل‌های عینی افزونه اجرا می‌شوند

## نتیجه نهایی: ✅ ۷۸۴ تست پاس — ۰ خطا (+ لینت PHP ۱۵/۱۵)

| سوئیت | پوشش | نتیجه |
|---|---|---|
| **test-realflow-admin** | **جریان لود واقعی wp-admin: فقط فایل اصلی افزونه لود می‌شود → init → admin_menu → رندر هر ۱۶ صفحه از طریق هوک‌های واقعی → admin_notices/admin_init/admin_enqueue_scripts** | **۱۳/۰** |
| **test-realflow-frontend** | جریان واقعی فرانت: init → wp_enqueue_scripts → شورت‌کد؛ کلاس ادمین روی فرانت لود نمی‌شود | ۷/۰ |
| **test-realflow-ajax** | جریان واقعی admin-ajax: DOING_AJAX → init → فراخوانی اندپوینت‌ها از رجیستری هوک (priv + nopriv) | ۱۰/۰ |
| test-bootstrap-file | فایل اصلی: فعال‌سازی روی DB خالی (۱۳ جدول + seed)، سیم‌کشی، روتین ارتقا، لینک‌ها | ۲۴/۰ |
| test-install | ساخت/تأیید/ترمیم شِما؛ ترتیب ستون‌ها؛ idempotency؛ ترمیم جدول/ستون/ایندکس/AUTO_INCREMENT؛ verify() | ۵۱/۰ |
| test-schema-parity | تطابق فیلد‌به‌فیلد: نام/ترتیب/تعریف کامل تک‌تک ستون‌ها + ایندکس‌ها + PK هر ۱۳ جدول؛ شمارش INSERT == ردیف‌های نصب | ۹۵/۰ |
| test-ajax | ۴ اندپوینت: nonce، get_data (+کش)، recommend، submit (اعتبارسنجی/کد پیگیری/ریت‌لیمیت)، ai_chat | ۴۶/۰ |
| **test-tier-ladder** | **نردبان پایدار اقتصادی/مدیریت‌شده/پیشرفته = ضعیف/میانی/قوی کاتالوگ — ماتریس ۵ هدف + چرخش موجودی + endpoint کامل + آیکون‌ها 🖨️/🏢/🚀 + ترتیب DOM برای RTL** | **۱۳۶/۰** |
| test-functions | ادغام تنظیمات، rate-limiter، mb truncate، اعتبارسنجی JSON، پاکسازی کش | ۲۵/۰ |
| test-crud | لیست/جستجو(XSS)/مرتب‌سازی/صفحه‌بندی؛ فرم؛ sanitize؛ درج/ویرایش/تکراری/حذف/گروهی؛ purge؛ گیت دسترسی | ۴۸/۰ |
| test-admin | منوها، asset شرطی، اعلان، ابزارها، وضعیت/حذف درخواست، sanitize تنظیمات | ۳۹/۰ |
| test-admin-screens | گشت کامل پنل: هر ۱۲ صفحه CRUD — لیست + فرم جدید + ویرایش + جستجو + کارت‌ها/دکمه‌های پولیش‌شده | ۱۹۲/۰ |
| test-shortcode | asset شرطی فقط هنگام رندر، FALNIC_SC_CONFIG، ریشه dir=rtl، گارد دوباره، aiEnabled، لوگوی سایت (fallback + custom_logo) | ۲۳/۰ |
| test-frontend-parity | ۱۱۰ id اصلی در app-shell؛ ارجاع‌های id در main.js معتبر؛ نقاط escape؛ بدون URL خارجی؛ رگرسیون shadow نشدن h()؛ هدر ثابت ۱۱ سکشن pro و ماندن ۴ آکاردئون سوالات | ۲۷/۰ |
| **test-css-coverage** | **قرارداد CSS دست‌نویس: هر کلاس استفاده‌شده در app-shell.php و main.js قانون دارد؛ isolation + RTL روی wrapper؛ بدون @import/URL خارجی** | **۳۱/۰** |
| test-uninstall | حذف optionها + transientها؛ drop جدول‌ها فقط با opt-in؛ بدون تنظیمات → داده محفوظ | ۱۵/۰ |

## تغییرات v1.1.0

1. **کرش «h is not a function» هنگام افزودن کارت HBA:** حلقهٔ renderHbas پارامترش را `h` نامیده بود و تابع escape سراسری h() را shadow می‌کرد. پارامتر به hbaRow تغییر کرد.
2. **سکشن‌های کانفیگوراتور پیشرفته دیگر باز/بسته نمی‌شوند:** ۱۱ سرصفحه به هدر ثابت تبدیل شد؛ کارت‌های سوالات نیازها (۴ عدد) عمداً آکاردئونی ماندند.
3. **لوگوی سایت:** سربرگ همهٔ مراحل حالا لوگوی سفارشی وردپرس (custom_logo) را نشان می‌دهد؛ بدون لوگوی سفارشی، لوگوی فالنیک می‌ماند.
4. **بازطراحی صفحه‌های مدیریت قطعات:** فهرست‌ها داخل کارت با هدر گرادیانی، دکمه‌های عملیات قرصی، حالت خالی دوستانه؛ فرم‌ها گرید مدرن.
5. **تمیزسازی مخزن:** فقط افزونه وردپرس در ریشه؛ مستندات README/AGENTS/DECISIONS داخل پلاگین.

## باگ‌های واقعی که پیدا و رفع شدند

1. `Falnic_SC_CRUD::header_cell()` — عدم‌تطابق آرگومان printf → ArgumentCountError در صفحه لیست (رفع: label و arrow دو آرگومان جدا)
2. `handle_request_status()` — whitelist حساس به بزرگی/کوچکی مقابل sanitize_key (رفع: نگاشت lowercase→استاندارد)
3. **چرخش tier پیشنهادها:** الگوریتم «نردبان پایدار» — اقتصادی = ضعیف‌ترین، پیشرفته = قوی‌ترین، مدیریت‌شده = میانی نزدیک target ×1.35
4. **استایل به‌هم‌ریخته دکمه‌ها و stepper:** CSS دست‌نویس ایزوله زیر `.falnic-sc-app` با `!important` و `isolation: isolate`
5. **Fatal روی staging:** `falnic_sc_catalog_defs()` undefined — هر فایل وابستگی خودش را require می‌کند؛ سوئیت‌های realflow فقط bootstrap را لود می‌کنند

## تطابق شِما

- ۱۳ جدول: Chassis, CPUs, RAMs, Storage_Drives, Storage_Controllers, GPUs, Network_Adapters, Risers, HBAs, Optical_Drives, Power_Supplies, Prepared_Server_Offers, User_Configurations
- تعریف ستون‌ها و ایندکس‌ها در `includes/falnic-sc-schema.php`
- شمارش INSERT seed == ردیف‌های پس از فعال‌سازی؛ جدول درخواست‌ها seed نمی‌شود
- `created_at/updated_at` از ادیتور مستثنا؛ بقیه ستون‌ها در پنل قابل ویرایش

## فرانت‌اند

- Markup در `templates/app-shell.php` با رپر `falnic-sc-app`
- `assets/main.js` روی همان IDها کار می‌کند
- چهار action AJAX + nonce وردپرس
- فایل‌ها کاملاً محلی: بدون CDN

## ساختار مخزن (پس از تمیزسازی)

```text
falnic-server-configurator/   ← ریشه مخزن = ریشه افزونه
├── falnic-server-configurator.php
├── uninstall.php
├── readme.txt
├── README.md / AGENTS.md / DECISIONS.md / TEST-REPORT.md
├── admin/  assets/  includes/  seed/  templates/
```
