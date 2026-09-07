# AGENTS.md — راهنمای کار روی افزونه

این سند برای توسعه‌دهندگان و Agentهایی است که روی مخزن **افزونه وردپرس کانفیگوراتور سرور فالنیک** کار می‌کنند.

هدف: حفظ ساختار پلاگین، جلوگیری از وابستگی runtime خارجی، هماهنگ نگه داشتن UI / AJAX / اسکیما / پنل / امنیت / مستندات.

---

## خلاصه سریع

| مورد | مقدار |
|---|---|
| نام | کانفیگوراتور سرور فالنیک (Falnic Server Configurator) |
| نوع | افزونه وردپرس |
| نسخه | 1.1.0 (`FALNIC_SC_VERSION`) / DB 1.0.0 (`FALNIC_SC_DB_VERSION`) |
| UI | فارسی، RTL، Vanilla JS + CSS لوکال |
| Backend | PHP روی WordPress (`$wpdb`, AJAX, options) |
| DB | MySQL/MariaDB — ۱۳ جدول کاتالوگ/درخواست |
| AI | OpenAI-compatible از طریق `Falnic_SC_AI` |
| شورت‌کد | `[falnic_server_configurator]` (+ alias `[hpe_server_configurator]`) |

این مخزن **فقط** پلاگین وردپرس است. نسخه مستقل PHP/استندالون دیگر بخشی از پروژه نیست.

---

## فایل‌های مهم

| مسیر | نقش |
|---|---|
| `falnic-server-configurator.php` | Bootstrap، ثابت‌ها، activation، lazy-load public/admin |
| `uninstall.php` | پاک‌سازی option/transient؛ drop جدول فقط با opt-in |
| `templates/app-shell.php` | Markup تمام Viewها و Modalها |
| `assets/main.js` | state، wizardها، configurator، validator، smartAssistant |
| `assets/js/security.js` | escape XSS |
| `assets/style.css` | CSS دست‌نویس ایزوله زیر `.falnic-sc-app` |
| `includes/class-falnic-sc-shortcode.php` | شورت‌کد + enqueue شرطی + `FALNIC_SC_CONFIG` |
| `includes/class-falnic-sc-ajax.php` | ۴ endpoint عمومی |
| `includes/class-falnic-sc-ajax-context.php` | Context و sanitize پاسخ AI |
| `includes/class-falnic-sc-ajax-text.php` | متن‌ها/promptهای AI |
| `includes/class-falnic-sc-ai.php` | فراخوانی Provider |
| `includes/class-falnic-sc-tables.php` | نام جدول + cast نوع ستون |
| `includes/falnic-sc-schema.php` | تعریف ستون/ایندکس هر جدول |
| `includes/class-falnic-sc-install.php` | verify/create/repair + seed |
| `includes/class-falnic-sc-admin.php` | منو، تنظیمات، ابزار، inbox |
| `includes/class-falnic-sc-crud.php` | CRUD کاتالوگ |
| `includes/falnic-sc-catalog-defs.php` | تعریف فیلدهای UI ادمین |
| `includes/falnic-sc-functions.php` | settings، rate limit، cache purge، logo |
| `seed/catalog-seed.sql` | INSERT اولیه (فقط جدول خالی) |
| `admin/css/admin.css`, `admin/js/admin.js` | UI پنل |

---

## مدل لود (خیلی مهم)

Bootstrap عمداً سبک است:

1. همیشه: `class-falnic-sc-tables.php` + `falnic-sc-functions.php`
2. `init` عمومی: shortcode؛ اگر `DOING_AJAX` → ajax
3. `init` ادمین: فقط `is_admin()` → admin (+ crud/catalog از داخل admin)
4. هر کلاس وابستگی‌های خودش را `require_once` می‌کند

**هرگز** فرض نکنید فایل‌ها از قبل لود شده‌اند. اگر کلاس جدیدی می‌سازید که از catalog/schema استفاده می‌کند، require را داخل همان فایل بگذارید. سوئیت‌های realflow فقط bootstrap اصلی را لود می‌کنند.

---

## State کلاینت

```js
const state = {
  sessionId: null,
  activeMode: 'pro',
  db: null,
  target: {},
  currentConfig: {},
  readyOffers: [],
  selectedOffer: null,
  currentView: 'view-intro'
};
```

| آبجکت | مسئولیت |
|---|---|
| `sessionManager` | Draft/Completed در `localStorage` |
| `api` | فراخوانی ۴ action AJAX با nonce |
| `validator` | بازخورد لحظه‌ای |
| `proWizard` / `wizard` | مراحل حرفه‌ای / راهنمایی |
| `configurator` | انتخاب قطعات + Summary |
| `smartAssistant` | Modal AI + actions |
| `uiRenderer` | پیش‌فاکتور / موفقیت |

ارتباط فرانت همیشه از `FALNIC_SC_CONFIG.ajaxUrl` و `nonce` است — URL اندپوینت را hardcode نکنید.

---

## قراردادهای فنی

### سازگاری قطعات

- `compatible_chassis_ids` خالی/null = همه
- فیلتر اولیه در `get_data`؛ UX محدودسازی در فرانت
- CPU socket و RAM generation باید با شاسی بخورند

### پیشنهاد سرور (نردبان پایدار)

`recommend` همیشه سه tier برمی‌گرداند:

- **eco** = ضعیف‌ترین آفر کاتالوگ (مرتب‌سازی score/generation/id)
- **advanced** = قوی‌ترین
- **managed** = آفر میانی نزدیک به target × 1.35

Tierها با تغییر target جابه‌جا نمی‌شوند (جلوگیری از چرخش کارت‌ها).

### AI

- بدون endpoint/key → `aiEnabled=false` و endpoint خطای تنظیمات
- Context whitelist؛ بدون Secret
- Action فقط از لیست مجاز در سرور + `applyAIAction()` فرانت
- Action جدید = تغییر همزمان AJAX sanitize و JS

### امنیت رندر

```js
h(text)                 // HTML text
security.escapeHTML()
security.attr()
security.inlineJson()
```

داده DB / پیام کاربر / پاسخ AI خام داخل `innerHTML` نرود.

**تله شناخته‌شده:** پارامتر حلقه را `h` نام نگذارید — تابع escape سراسری shadow می‌شود (باگ HBA در v1.1.0).

### CSS

- همه utilityها در `assets/style.css`
- سلکتورها زیر `.falnic-sc-app`
- declarationها با `!important` برای مقاومت در برابر قالب
- `isolation: isolate` روی wrapper
- بدون `@import` و URL خارجی
- کلاس جدید در markup/JS باید در CSS تعریف شود

### اسکیما / نصب

- منبع حقیقت ستون‌ها: `includes/falnic-sc-schema.php`
- seed فقط وقتی جدول خالی است
- `User_Configurations` هرگز seed نمی‌شود
- ارتقا: اگر `falnic_sc_db_version` ≠ `FALNIC_SC_DB_VERSION` → `install()` دوباره
- فیلتر `falnic_sc_table_name` برای rename فیزیکی

### تنظیمات (`falnic_sc_settings`)

```text
ai_enabled, ai_endpoint, ai_api_key, ai_model,
ai_timeout, ai_max_tokens, ai_temperature,
cache_ttl, ai_rate_limit, submit_rate_limit,
delete_data_on_uninstall
```

---

## قواعد تغییر کد

### قبل از تغییر

1. `README.md` / `AGENTS.md` / `DECISIONS.md` / `readme.txt` را ببینید
2. محدوده اثر: UI، JS state، AJAX، schema، admin CRUD، AI، CSS، docs
3. ساختار اصلی کانفیگوراتور را بدون نیاز واقعی نشکنید

### JS

- بعد از تغییر قطعه معمولاً `configurator.calculateSummary()`
- View/Step جدید → `state.currentView` + Context AI
- Action AI جدید → whitelist سرور + `applyAIAction`
- Dynamic HTML → escape

### AJAX / PHP

- Prepared statements از طریق `$wpdb->prepare`
- ورودی cast/validate
- قرارداد JSON پایدار بماند (فرانت وابسته است)
- منطق حیاتی فقط سمت سرور

### اسکیما

- `falnic-sc-schema.php` + در صورت نیاز `seed/catalog-seed.sql`
- نام/ترتیب ستون با تست parity هم‌خوان بماند
- تغییر نام جدول/ستون = PHP + JS + docs همزمان

### ادمین

- فیلد جدید در `falnic-sc-catalog-defs.php`
- sanitize در CRUD
- پس از ذخیره → `falnic_sc_purge_data_cache()`

### CSS

- کلاس جدید را به `style.css` اضافه کنید
- از utilityهای ساختگی Tailwind که در فایل نیستند استفاده نکنید

---

## راه‌اندازی توسعه

1. وردپرس لوکال (wp-env، Local, Laravel Valet, Docker, …)
2. symlink یا کپی مخزن به `wp-content/plugins/falnic-server-configurator`
3. فعال‌سازی افزونه
4. برگه با شورت‌کد
5. AI اختیاری از تنظیمات

```bash
php -l falnic-server-configurator.php
find includes templates admin -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/main.js
node --check assets/js/security.js
node --check admin/js/admin.js
```

---

## چک‌لیست QA

### عمومی

- [ ] Syntax PHP/JS سالم
- [ ] فعال‌سازی روی DB خالی
- [ ] ارتقا نسخه DB
- [ ] شورت‌کد asset شرطی
- [ ] Intro / pro / guidance
- [ ] سه پیشنهاد پایدار
- [ ] Validator سخت‌افزاری
- [ ] Submit → tracking code
- [ ] AI history + action
- [ ] CRUD + cache purge
- [ ] Requests status/delete
- [ ] Tools: repair / seed / purge
- [ ] Uninstall بدون opt-in داده را نگه می‌دارد
- [ ] لوگوی custom_logo / fallback
- [ ] هدرهای pro ثابت (نه accordion)؛ سؤالات guidance آکاردئونی

### سخت‌افزار

- [ ] CPU ناسازگار رد شود
- [ ] بیش‌ازحد سوکت/slot/bay خطا
- [ ] RAID پیشرفته بدون controller مناسب خطا
- [ ] FlexibleLOM > 1 خطا
- [ ] Riser3 بدون CPU2 خطا
- [ ] PSU ضعیف خطا

### تعریف «تغییر کامل‌شده»

1. Syntax سالم
2. مسیر کاربری مرتبط smoke شده
3. AJAX معتبر/نامعتبر بررسی شده
4. XSS / Secret / URL خارجی در نظر گرفته شده
5. Docs مرتبط به‌روز
6. اگر schema عوض شد، seed/install هماهنگ است

---

## بدهی‌های فنی شناخته‌شده

- `assets/main.js` بزرگ است؛ ماژولار شدن تدریجی
- تست خودکار CI در مخزن اصلی هنوز formal نیست (گزارش در `TEST-REPORT.md`)
- قیمت بسیاری از قطعات `NULL` → `price_incomplete`
- PDF آرشیوی سمت سرور وجود ندارد (چاپ مرورگر)
- Draft فقط `localStorage` است
- Ruleهای AI/پیشنهاد با داده واقعی فروش قابل بهبودند

---

## نگهداری مستندات

| تغییر | فایل |
|---|---|
| رفتار محصول / نصب | `README.md` + در صورت نیاز `readme.txt` |
| قرارداد dev/QA | `AGENTS.md` |
| تصمیم معماری/امنیتی | `DECISIONS.md` |
| نتیجه تست بزرگ | `TEST-REPORT.md` |
| Changelog انتشار | `readme.txt` + ثابت `FALNIC_SC_VERSION` |

Secret واقعی در هیچ Markdownای نوشته نشود.
