# AGENTS.md — راهنمای کار روی پروژه

این سند برای توسعه‌دهندگان و Agentهایی است که روی Repository کار می‌کنند. هدف، حفظ ساختار اصلی کانفیگوراتور، جلوگیری از برگشت وابستگی‌های خارجی و هماهنگ نگه داشتن UI، API، دیتابیس، امنیت و مستندات است.

---

## خلاصه سریع پروژه

- نام کاربردی: **کانفیگوراتور حرفه‌ای سرور HPE فالنیک**
- نوع پروژه: PHP ساده + Vanilla JavaScript + CSS لوکال
- زبان و جهت UI: فارسی، RTL
- دیتابیس: MySQL/MariaDB
- AI: سرویس OpenAI-compatible از طریق `api/ai_chat.php`
- هدف: نیازسنجی، پیشنهاد سرور آماده، انتخاب قطعات، اعتبارسنجی سخت‌افزاری و ثبت پیش‌فاکتور

---

## فایل‌های مهم

| فایل/پوشه | نقش |
|---|---|
| `index.php` | Markup تمام Viewها، Modalها، دکمه‌های راهنمای هوشمند و اتصال CSS/JS |
| `assets/main.js` | منطق اصلی کلاینت، state، wizardها، configurator، validator، smart assistant |
| `assets/js/security.js` | helperهای escape برای جلوگیری از XSS در رندرهای Dynamic |
| `assets/style.css` | Utility CSS لوکال، استایل‌های اختصاصی، فونت، Modalها و responsive |
| `api/get_data.php` | دریافت لیست شاسی‌ها و قطعات سازگار با شاسی |
| `api/recommend_servers.php` | انتخاب دقیقاً سه سرور آماده اقتصادی/مدیریت‌شده/پیشرفته |
| `api/submit_config.php` | اعتبارسنجی نهایی، محاسبه توان/قیمت و ثبت کانفیگ |
| `api/ai_chat.php` | Gateway امن AI با Context محدود، history، quick reply و action |
| `config/database.php` | تنها نقطه ساخت PDO و خواندن تنظیمات دیتابیس |
| `config/ai.php` | تنظیمات سرویس AI از ENV یا فایل local |
| `config/secrets.local.example.php` | نمونه فایل یکپارچه رمزها و کلیدها |
| `database/migrations/` | Migrationهای جداگانه برای دیتابیس‌های موجود |
| `falnicc1_server_configurator.sql` | Dump کامل اسکیمای دیتابیس و seedها |
| `README.md` | مستند محصول، نصب، استقرار و APIها |
| `DECISIONS.md` | تصمیمات معماری ADR |

---

## فایل‌های محرمانه و Git Ignore

این فایل‌ها هرگز نباید Commit شوند:

```text
.env
config/database.local.php
config/ai.local.php
config/secrets.local.php
```

روش پیشنهادی برای سرور:

```bash
cp config/secrets.local.example.php config/secrets.local.php
```

سپس اطلاعات واقعی دیتابیس و AI را فقط در فایل local یا ENV قرار دهید.

---

## مدل ذهنی کلاینت

### State اصلی

`state` در `assets/main.js` منبع اصلی وضعیت جاری است:

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

- `state.db`: داده‌های قطعات دریافت‌شده از API.
- `state.target`: نیاز فنی کاربر.
- `state.currentConfig`: کانفیگ جاری قابل ثبت.
- `state.readyOffers`: سه پیشنهاد آماده.
- `state.selectedOffer`: پیشنهاد انتخاب‌شده از مسیر راهنمایی یا AI.
- `state.currentView`: صفحه فعلی برای Context AI.

### آبجکت‌های اصلی در `main.js`

| آبجکت/تابع | مسئولیت |
|---|---|
| `sessionManager` | ذخیره/بازیابی Draft و Completed در `localStorage` |
| `uiRenderer` | تولید پیش‌فاکتور و Modal موفقیت |
| `guidanceConfig` | تعریف سؤال‌های مسیر راهنمایی |
| `calculatePcieUsage` | محاسبه ظرفیت و مصرف PCIe |
| `api` | ارتباط با APIهای PHP |
| `validator` | پیام‌های اعتبارسنجی و Highlight فیلدها |
| `proWizard` | مراحل مسیر حرفه‌ای |
| `wizard` | مدیریت Viewها، مسیر راهنمایی، پیشنهادها و انتخاب offer |
| `configurator` | انتخاب قطعات، رندر ردیف‌های داینامیک و Summary |
| `smartAssistant` | Modal AI، history، quick replies و actionهای قابل اعمال |

---

## قراردادهای فنی

### سازگاری قطعات

- `compatible_chassis_ids = NULL` یا `[]` یعنی سازگار با همه شاسی‌ها.
- CPU باید با `cpu_socket_type` شاسی همخوان باشد.
- RAM باید با `ram_generation` شاسی همخوان باشد.
- اگر RAM دارای `compatible_cpu_ids` است، CPU انتخابی باید داخل آن باشد.
- فیلتر اولیه در API انجام می‌شود، ولی فرانت‌اند هم برای UX محدودسازی می‌کند.

### دریافت داده

- شروع مسیر حرفه‌ای: `GET api/get_data.php` فقط شاسی‌ها را می‌گیرد.
- بعد از انتخاب شاسی: `GET api/get_data.php?chassis_id=<id>` قطعات سازگار را می‌گیرد.
- انتخاب پیشنهاد آماده: `offer.db` به کمک `wizard.mergeOfferDb()` داخل `state.db` merge می‌شود.
- اگر action AI به قطعه‌ای نیاز داشته باشد که در `state.db` نیست، `smartAssistant.ensureActionData()` قطعات همان شاسی را Load می‌کند.

### پیشنهاد سرور آماده

- `api/recommend_servers.php` باید همیشه دقیقاً سه سطح `eco`, `managed`, `advanced` برگرداند، مگر خطای واقعی API.
- Ruleها باید ظرفیت، workload، GPU، رشد آینده، شبکه، موجودی و زمان تأمین را در نظر بگیرند.
- پیشنهاد انتخاب‌شده باید به ساختار `currentConfig` قابل ویرایش تبدیل شود.

### AI و actionها

- `api/ai_chat.php` نباید کل دیتابیس یا کل state خام را ارسال کند.
- Context ارسالی باید whitelist شده، کوتاه و بدون Secret باشد.
- پاسخ AI باید به JSON ساختاریافته تبدیل شود: `reply`, `question`, `quick_replies`, `actions`.
- اگر Provider متن غیر JSON بدهد، endpoint باید آن را پاکسازی و کوتاه کند.
- Actionها فقط از لیست امن مجاز هستند.

Actionهای مجاز:

```text
set_cpu
set_ram
set_cpu_ram
set_ram_qty
set_ram_total
set_cpu_qty
set_psu_qty
add_drive_raid10
```

- Quick Reply ممکن است فقط پیام بفرستد یا همراه action باشد.
- دکمه‌هایی مثل «بله، اول CPU و رم را اصلاح کن» باید یا action همراه را اعمال کنند یا پیام را ارسال کنند و action برگشتی را خودکار اعمال کنند.

### امنیت رندر

- برای متن dynamic از `h()` یا `security.escapeHTML()` استفاده کنید.
- برای attribute از `security.attr()` استفاده کنید.
- برای مقدار JS inline از `security.inlineJson()` استفاده کنید.
- داده دیتابیس، پیام کاربر و پاسخ AI هرگز خام داخل `innerHTML` قرار نگیرد.

---

## راه‌اندازی توسعه

### دیتابیس

```bash
mysql --default-character-set=utf8mb4 -u <db_user> -p falnicc1_server_configurator < falnicc1_server_configurator.sql
```

برای دیتابیس‌های موجود:

```bash
mysql --default-character-set=utf8mb4 -u <db_user> -p falnicc1_server_configurator < database/migrations/2026_09_06_create_prepared_server_offers.sql
```

### تنظیمات local

روش پیشنهادی:

```bash
cp config/secrets.local.example.php config/secrets.local.php
```

یا جداگانه:

```bash
cp config/database.local.example.php config/database.local.php
cp config/ai.local.example.php config/ai.local.php
```

### سرور توسعه

```bash
php -S 0.0.0.0:8000
```

---

## وابستگی‌های UI و برندینگ

- Tailwind CDN حذف شده و نباید دوباره اضافه شود.
- هیچ CSS/JS runtime خارجی نباید اضافه شود مگر تصمیم معماری جدید ثبت شود.
- CSS باید در `assets/style.css` نگهداری شود.
- فونت اصلی از `assets/falnic-font.woff2` خوانده می‌شود.
- لوگوی فالنیک: `assets/falnic-logo.svg`.
- لوگوی HPE: `assets/hpe-logo.svg`.
- هر asset خارجی ابتدا باید local شود.

---

## نکات امنیتی مهم

- Secret واقعی را در کد، Markdown، Issue، PR، Log یا نمونه‌ها ذخیره نکنید.
- فایل‌های local محرمانه در `.gitignore` هستند؛ آن‌ها را force add نکنید.
- اتصال دیتابیس فقط از `config/database.php` و `databaseConnection()` ساخته شود.
- تنظیمات AI فقط از `config/ai.php` خوانده شود.
- `api/submit_config.php` آخرین خط دفاعی اعتبارسنجی است.
- خطاهای داخلی دیتابیس/AI در Production نباید با جزئیات حساس به کاربر نمایش داده شوند.
- خروجی JSON باید `JSON_UNESCAPED_UNICODE` و header مناسب داشته باشد.

---

## قواعد تغییر کد

### قبل از تغییر

- `README.md`, `AGENTS.md`, `DECISIONS.md` را بررسی کنید.
- مشخص کنید تغییر روی کدام بخش اثر دارد: UI، JS State، API، DB، AI، Security یا Docs.
- ساختار اصلی کانفیگوراتور را بدون نیاز واقعی تغییر ندهید.

### هنگام تغییر JS

- بعد از تغییر قطعه، معمولاً `configurator.calculateSummary()` لازم است.
- اگر اعتبارسنجی اثر می‌گیرد، `validator.runChecks()` را در نظر بگیرید.
- اگر View یا Step جدید اضافه می‌شود، `state.currentView` و Context AI را به‌روزرسانی کنید.
- اگر action AI جدید اضافه می‌شود، هم whitelist سرور و هم `applyAIAction()` فرانت‌اند را هماهنگ کنید.
- برای Dynamic HTML حتماً escape انجام دهید.

### هنگام تغییر API

- از Prepared Statement استفاده کنید.
- ورودی‌ها را cast/validate کنید.
- خروجی JSON ساختار پایدار داشته باشد.
- خطاها را با HTTP status مناسب برگردانید.
- منطق حیاتی را فقط به فرانت‌اند نسپارید.

### هنگام تغییر دیتابیس

- Dump کامل و Migration جداگانه را به‌روزرسانی کنید.
- اگر جدول یا ستون جدید به UI/API وابسته است، docs و seedها را هم به‌روز کنید.
- JSONها باید معتبر باشند.
- تغییر نام جدول/ستون نیازمند تغییر همزمان PHP/JS است.

---

## چک‌لیست QA بعد از تغییر

### تست‌های عمومی

```bash
node --check assets/js/security.js
node --check assets/main.js
git diff --check
```

اگر PHP CLI در دسترس است:

```bash
php -l api/get_data.php
php -l api/recommend_servers.php
php -l api/submit_config.php
php -l api/ai_chat.php
php -l config/database.php
php -l config/ai.php
```

### UI و جریان‌ها

- [ ] صفحه Intro باز می‌شود.
- [ ] مسیر حرفه‌ای شروع می‌شود.
- [ ] شاسی‌ها از API دریافت می‌شوند.
- [ ] انتخاب شاسی باعث Load قطعات سازگار می‌شود.
- [ ] Summary پس از تغییر CPU/RAM/Storage/GPU/PSU به‌روز می‌شود.
- [ ] Wizard حرفه‌ای قابل رفت‌وبرگشت است.
- [ ] Draft ذخیره و بازیابی می‌شود.
- [ ] مسیر راهنمایی تا صفحه سه پیشنهاد کامل می‌شود.
- [ ] کلاس اضافه `space-y-3 mb-6` روی لیست bullet پیشنهادهای مسیر راهنمایی برنگردد.
- [ ] انتخاب هر پیشنهاد، صفحه جزئیات را بدون خطا باز می‌کند.
- [ ] Smart Assistant در Modal باز و بسته می‌شود.
- [ ] انتخاب پیشنهاد داخل Smart Assistant خطا نمی‌دهد.
- [ ] History چت بعد از بستن/باز کردن Modal حفظ می‌شود.
- [ ] Quick Reply پیام را در همان چت ادامه می‌دهد.
- [ ] Actionهایی مثل `set_cpu_ram` واقعاً CPU/RAM را در کانفیگ تغییر می‌دهند.
- [ ] ثبت نهایی کد رهگیری تولید می‌کند.

### اعتبارسنجی سخت‌افزاری

- [ ] CPU ناسازگار با شاسی قابل ثبت نیست.
- [ ] تعداد CPU بیشتر از ظرفیت شاسی خطا می‌دهد.
- [ ] RAM ناسازگار با شاسی یا CPU خطا می‌دهد.
- [ ] RAM بیشتر از Slot فعال خطا می‌دهد.
- [ ] RAID پیشرفته بدون Controller مناسب/SAS Expander خطا می‌دهد.
- [ ] تعداد Drive بیش از Bay/Controller خطا می‌دهد.
- [ ] GPU بدون Riser مناسب خطا می‌دهد.
- [ ] Riser سوم بدون CPU دوم خطا می‌دهد.
- [ ] FlexibleLOM بیشتر از یک کارت خطا می‌دهد.
- [ ] PSU ضعیف نسبت به توان امن خطا می‌دهد.

### Backend/API

- [ ] `GET api/get_data.php` JSON معتبر می‌دهد.
- [ ] `GET api/get_data.php?chassis_id=<id>` قطعات سازگار می‌دهد.
- [ ] `POST api/recommend_servers.php` دقیقاً سه offer می‌دهد.
- [ ] `POST api/ai_chat.php` با config درست پاسخ AI ساختاریافته می‌دهد.
- [ ] `POST api/submit_config.php` کانفیگ معتبر را ثبت می‌کند.
- [ ] `POST api/submit_config.php` کانفیگ نامعتبر را رد می‌کند.

---

## بدهی‌های فنی شناخته‌شده

- `assets/main.js` هنوز بزرگ است و باید تدریجی ماژولار شود.
- تست خودکار/CI هنوز تعریف نشده است.
- قیمت بسیاری از قطعات `NULL` است و نیاز به مدیریت قیمت دارد.
- پنل مدیریت قطعات، موجودی و پیشنهادهای آماده وجود ندارد.
- PDF واقعی سمت سرور/قابل آرشیو هنوز پیاده‌سازی نشده است.
- Ruleهای AI و پیشنهاددهی باید با داده واقعی فروش/موجودی بهبود پیدا کنند.

---

## تعریف «تغییر کامل‌شده»

یک تغییر کامل است اگر:

1. Syntax JS و در صورت امکان PHP سالم باشد.
2. مسیر کاربری مرتبط دستی یا smoke تست شده باشد.
3. API مرتبط با ورودی معتبر/نامعتبر بررسی شده باشد.
4. XSS/Secret/External URL scan در نظر گرفته شده باشد.
5. مستندات مرتبط در README/AGENTS/DECISIONS به‌روز شده باشند.
6. اگر اسکیمای DB تغییر کرده، Migration جداگانه اضافه شده باشد.

---

## نگهداری مستندات

- تغییر رفتار محصول → `README.md`.
- تغییر قرارداد توسعه/QA → `AGENTS.md`.
- تصمیم معماری یا امنیتی جدید → `DECISIONS.md`.
- Secret واقعی هرگز در هیچ فایل Markdown نوشته نشود.
