# DECISIONS.md — دفترچه تصمیمات معماری (ADR)

تصمیمات فنی افزونه وردپرس «کانفیگوراتور سرور فالنیک» برای پیگیری دلیل وضعیت فعلی.

---

## فهرست

| شناسه | عنوان | وضعیت |
|---|---|---|
| [ADR-0001](#adr-0001--افزونه-وردپرس-به‌عنوان-تنها-محصول-مخزن) | افزونه وردپرس به‌عنوان تنها محصول مخزن | Accepted |
| [ADR-0002](#adr-0002--php--vanilla-js-بدون-build-اجباری) | PHP + Vanilla JS بدون build اجباری | Accepted |
| [ADR-0003](#adr-0003--جدولهای-همنام-با-کاتالوگ-اصلی) | جدول‌های همنام با کاتالوگ اصلی | Accepted |
| [ADR-0004](#adr-0004--ساخت-و-ترمیم-خودکار-اسکیما) | ساخت و ترمیم خودکار اسکیما | Accepted |
| [ADR-0005](#adr-0005--دریافت-مرحلهای-داده-بر-اساس-شاسی) | دریافت مرحله‌ای داده بر اساس شاسی | Accepted |
| [ADR-0006](#adr-0006--ستونهای-json-برای-قوانین-سازگاری) | ستون‌های JSON برای قوانین سازگاری | Accepted |
| [ADR-0007](#adr-0007--اعتبارسنجی-دو-لایه) | اعتبارسنجی دو لایه | Accepted |
| [ADR-0008](#adr-0008--ajax-وردپرس-به‌جای-endpoint-مستقل) | AJAX وردپرس به‌جای endpoint مستقل | Accepted |
| [ADR-0009](#adr-0009--بارگذاری-شرطی-asset-با-شورتکد) | بارگذاری شرطی asset با شورت‌کد | Accepted |
| [ADR-0010](#adr-0010--css-دستنویس-ایزوله-در-برابر-قالب) | CSS دست‌نویس ایزوله در برابر قالب | Accepted |
| [ADR-0011](#adr-0011--نردبان-پایدار-پیشنهادهای-آماده) | نردبان پایدار پیشنهادهای آماده | Accepted |
| [ADR-0012](#adr-0012--ai-openai-compatible-با-context-محدود) | AI OpenAI-compatible با Context محدود | Accepted |
| [ADR-0013](#adr-0013--quick-replies-و-actionهای-whitelist) | Quick Replies و Actionهای whitelist | Accepted |
| [ADR-0014](#adr-0014--پنل-مدیریت-کامل-کاتالوگ-داخل-wp-admin) | پنل مدیریت کامل کاتالوگ داخل wp-admin | Accepted |
| [ADR-0015](#adr-0015--تنظیمات-و-secret-داخل-options-وردپرس) | تنظیمات و Secret داخل options وردپرس | Accepted |
| [ADR-0016](#adr-0016--uninstall-محافظهکارانه) | Uninstall محافظه‌کارانه | Accepted |
| [ADR-0017](#adr-0017--bootstrap-سبک-و-require-وابستگی-محلی) | Bootstrap سبک و require وابستگی محلی | Accepted |
| [ADR-0018](#adr-0018--لوگوی-سایت-وردپرس-در-سربرگ-اپ) | لوگوی سایت وردپرس در سربرگ اپ | Accepted |

---

## ADR-0001 — افزونه وردپرس به‌عنوان تنها محصول مخزن

**وضعیت:** Accepted  
**تاریخ:** 2026-09-07

### زمینه

قبلاً نسخه مستقل PHP (`index.php` + `api/` + `config/`) کنار پوشه پلاگین در یک مخزن بودند. نگهداری دو سطح استقرار باعث دوباره‌کاری و ابهام مسیر توسعه می‌شد.

### تصمیم

مخزن فقط افزونه وردپرس را نگه می‌دارد. فایل‌های نسخه مستقل حذف شدند. مستندات محصول/توسعه/ADR همگی داخل ریشه پلاگین هستند.

### پیامدها

- یک مسیر نصب: `wp-content/plugins/falnic-server-configurator`
- توسعه و QA روی یک codebase
- اگر روزی نسخه غیروردپرسی لازم شود، باید fork/مخزن جدا با ADR جدید باشد

---

## ADR-0002 — PHP + Vanilla JS بدون build اجباری

**وضعیت:** Accepted  
**تاریخ:** 2026-09-05

### زمینه

باید روی هاست‌های وردپرسی معمولی بدون Node build در production کار کند.

### تصمیم

- Backend: PHP کلاس‌محور روی API وردپرس
- Frontend: Vanilla JS در `assets/main.js`
- Markup: `templates/app-shell.php`

### پیامدها

**مزایا:** استقرار ساده، وابستگی صفر runtime.  
**معایب:** `main.js` بزرگ می‌شود؛ ماژولار شدن تدریجی (ADR آینده).

---

## ADR-0003 — جدول‌های همنام با کاتالوگ اصلی

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### زمینه

کاتالوگ قطعات و دامپ‌های قبلی با نام‌هایی مثل `Chassis`, `CPUs` وجود داشتند. Prefix اجباری `$wpdb->prefix` مهاجرت داده را سخت می‌کرد.

### تصمیم

نام فیزیکی جدول‌ها بدون prefix پیش‌فرض وردپرس نگه داشته می‌شود. فیلتر `falnic_sc_table_name` برای سایت‌هایی که prefix می‌خواهند موجود است.

### پیامدها

- Import دامپ قدیمی ممکن است
- باید مراقب collision نام جدول در DBهای شلوغ بود
- cast نوع ستون‌ها در `Falnic_SC_Tables` قرارداد JSON فرانت را پایدار نگه می‌دارد

---

## ADR-0004 — ساخت و ترمیم خودکار اسکیما

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### زمینه

فعال‌سازی دستی SQL روی همه محیط‌ها عملی نیست؛ ارتقا باید ستون/ایندکس ناقص را تعمیر کند.

### تصمیم

`Falnic_SC_Installer::install()` در activation، mismatch نسخه DB، و ابزار ادمین:

1. ساخت جدول مفقود  
2. افزودن ستون مفقود  
3. افزودن ایندکس مفقود  
4. تعمیر AUTO_INCREMENT  
5. seed جدول خالی از `seed/catalog-seed.sql`

تعریف منبع: `includes/falnic-sc-schema.php`

### پیامدها

- Deploy امن‌تر
- تغییر اسکیما = به‌روز کردن schema PHP (+ seed در صورت نیاز)
- روی MySQL قدیمی CHECK constraint ممکن است strip شود

---

## ADR-0005 — دریافت مرحله‌ای داده بر اساس شاسی

**وضعیت:** Accepted  
**تاریخ:** 2026-09-05

### تصمیم

- بدون `chassis_id`: فقط شاسی‌ها
- با `chassis_id`: قطعات سازگار همان شاسی
- نتیجه cache می‌شود؛ CRUD ادمین cache را purge می‌کند

### پیامدها

حجم پاسخ کمتر؛ تغییر شاسی نیازمند reset بخشی از state؛ AI action در صورت نیاز data را دوباره load می‌کند.

---

## ADR-0006 — ستون‌های JSON برای قوانین سازگاری

**وضعیت:** Accepted  
**تاریخ:** 2026-09-05

### تصمیم

`storage_rules`, `cooling_rules`, `compatible_chassis_ids`, `compatible_cpu_ids`, `selected_components`, `bullets`, … به‌صورت JSON ذخیره و در PHP decode می‌شوند. ادیتور ادمین با `falnic_sc_validate_json_field()` اعتبارسنجی می‌کند.

### پیامدها

انعطاف Rule بدون migration ستونی؛ نیاز به JSON معتبر در seed و UI.

---

## ADR-0007 — اعتبارسنجی دو لایه

**وضعیت:** Accepted  
**تاریخ:** 2026-09-05 · به‌روزرسانی 2026-09-06

### تصمیم

- فرانت: `validator.runChecks()` برای UX
- بک‌اند: `submit_config` قطعات را دوباره از DB می‌خواند (RAID، bay، RAM slot، PCIe/Riser، GPU، PSU، …)

### پیامدها

Rule جدید باید در هر دو لایه یا با قرارداد مشترک هماهنگ شود.

---

## ADR-0008 — AJAX وردپرس به‌جای endpoint مستقل

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

چهار action:

```text
falnic_sc_get_data
falnic_sc_recommend
falnic_sc_submit
falnic_sc_ai_chat
```

روی `admin-ajax.php` با نونس `falnic_sc_public` (priv + nopriv). قرارداد پاسخ با فرانت `main.js` پایدار است.

### پیامدها

امنیت و یکپارچگی با WP؛ وابستگی به admin-ajax (در ترافیک بالا می‌توان REST را در ADR جدا بررسی کرد).

---

## ADR-0009 — بارگذاری شرطی asset با شورت‌کد

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

Register سراسری، enqueue فقط در `Falnic_SC_Shortcode::render`. گارد ضد رندر تکراری. نسخه فایل از `filemtime`.

### پیامدها

صفر overhead CSS/JS روی بقیه سایت؛ اگر شورت‌کد در builderهایی که render را دیر اجرا می‌کنند استفاده شود، باید enqueue زودهنگام جداگانه بررسی شود.

---

## ADR-0010 — CSS دست‌نویس ایزوله در برابر قالب

**وضعیت:** Accepted  
**تاریخ:** 2026-09-07

### زمینه

زیرمجموعه ناقص utilityهای Tailwind + specificity قالب‌های سنگین → دکمه‌ها و stepper خراب می‌شدند؛ `h-0.5` و stacking خط stepper مشکل داشت.

### تصمیم

- حذف کامل وابستگی ذهنی به Tailwind CDN/utility ناقص
- `assets/style.css` دست‌نویس، همه زیر `.falnic-sc-app`
- `!important` گسترده + `isolation: isolate` + `direction: rtl`
- لوگو لوکال؛ فونت از قالب/سایت (بدون bundled @font-face)

### پیامدها

مقاومت بالا در برابر قالب؛ هر کلاس جدید باید صریحاً تعریف شود؛ فایل CSS نسبتاً بزرگ است.

---

## ADR-0011 — نردبان پایدار پیشنهادهای آماده

**وضعیت:** Accepted  
**تاریخ:** 2026-09-07

### زمینه

با فیلتر «پوشش target»، آفر ضعیف از eco می‌افتاد و کارت‌ها بین بخش‌ها می‌چرخیدند.

### تصمیم

مرتب‌سازی آفرها بر اساس `(performance_score, generation_rank, id)`:

- اقتصادی = ضعیف‌ترین
- پیشرفته = قوی‌ترین
- مدیریت‌شده = میانی نزدیک به target × 1.35

### پیامدها

UX پایدار در گرید RTL؛ کیفیت به seed و scoreها وابسته است.

---

## ADR-0012 — AI OpenAI-compatible با Context محدود

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

- `Falnic_SC_AI` به endpoint سازگار با OpenAI Chat Completions وصل می‌شود
- تنظیمات از `falnic_sc_settings`
- بدون key/endpoint → غیرفعال
- Context فقط whitelist (مرحله، target، selection، validator، گزینه‌ها، offers، history کوتاه)
- پاسخ باید JSON ساختاریافته شود؛ متن آزاد پاکسازی/کوتاه می‌شود

### پیامدها

پاسخ نزدیک به وضعیت کاربر؛ کیفیت وابسته به Provider/prompt؛ rate limit جدا.

---

## ADR-0013 — Quick Replies و Actionهای whitelist

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

```json
{
  "reply": "",
  "question": "",
  "quick_replies": [],
  "actions": []
}
```

Actionهای مجاز:

```text
set_cpu, set_ram, set_cpu_ram, set_ram_qty, set_ram_total,
set_cpu_qty, set_psu_qty, add_drive_raid10
```

### پیامدها

دکمه‌های AI تغییر واقعی روی کانفیگ می‌دهند؛ action آزاد اجرا نمی‌شود؛ action جدید = سرور + فرانت همزمان.

---

## ADR-0014 — پنل مدیریت کامل کاتالوگ داخل wp-admin

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06 · UI polish 2026-09-07

### تصمیم

منوی سطح‌بالا با CRUD برای ۱۲ موجودیت + inbox درخواست‌ها + تنظیمات + ابزار DB. تعریف فیلدها در `falnic-sc-catalog-defs.php`؛ منطق مشترک در `Falnic_SC_CRUD`.

### پیامدها

مدیریت بدون phpMyAdmin؛ سطح دسترسی فعلاً `manage_options` (نقش فروش جدا در آینده).

---

## ADR-0015 — تنظیمات و Secret داخل options وردپرس

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

به‌جای فایل `secrets.local.php` نسخه مستقل، همه تنظیمات (شامل API key) در option `falnic_sc_settings` با sanitize در ادمین.

### پیامدها

سازگار با مدل وردپرس؛ در multisite باید به scope شبکه/سایت توجه شود؛ backup DB شامل key می‌شود — دسترسی سرور را محدود کنید.

---

## ADR-0016 — Uninstall محافظه‌کارانه

**وضعیت:** Accepted  
**تاریخ:** 2026-09-06

### تصمیم

پیش‌فرض: حذف option/transient؛ **حفظ** جدول‌های کاتالوگ و درخواست‌ها. Drop فقط اگر `delete_data_on_uninstall` فعال باشد.

### پیامدها

re-install داده را از دست نمی‌دهد؛ پاک‌سازی کامل صریح و آگاهانه است.

---

## ADR-0017 — Bootstrap سبک و require وابستگی محلی

**وضعیت:** Accepted  
**تاریخ:** 2026-09-07

### زمینه

خطای Fatal staging: `falnic_sc_catalog_defs()` undefined — فایل catalog فقط در bootstrap تست لود می‌شد، نه در زنجیره واقعی `admin_menu`.

### تصمیم

- فایل اصلی فقط tables + functions + hookها
- admin/ajax/install/crud هرکدام require خود را دارند
- تست realflow فقط bootstrap را لود و چرخه هوک WP را شبیه‌سازی می‌کند

### پیامدها

overhead کم روی درخواست‌های نامرتبط؛ فراموشی require سریع‌تر در realflow دیده می‌شود.

---

## ADR-0018 — لوگوی سایت وردپرس در سربرگ اپ

**وضعیت:** Accepted  
**تاریخ:** 2026-09-07

### تصمیم

`falnic_sc_site_logo_url()` ابتدا `custom_logo` تم را می‌خواند؛ fallback لوگوی bundled فالنیک.

### پیامدها

برندینگ مشتری بدون فیلد جدا؛ وابستگی به theme mod استاندارد WP.

---

## تصمیمات آینده که باید ثبت شوند

- ماژولار کردن کامل `assets/main.js`
- REST API به‌جای/کنار admin-ajax
- نقش و capability جدا برای فروش/انبار
- Draft سمت سرور به‌جای فقط localStorage
- PDF آرشیوی
- مدل دقیق‌تر RAID/Backplane/NVMe
- CI رسمی در مخزن
- سیاست logging امن خطاهای AI/DB
- i18n کامل با فایل‌های ترجمه (`/languages`)
