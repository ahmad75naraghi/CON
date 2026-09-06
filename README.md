# کانفیگوراتور حرفه‌ای سرور HPE فالنیک

وب‌اپلیکیشن فارسی و راست‌به‌چپ برای نیازسنجی، پیشنهاد سرور آماده، انتخاب قطعات HPE ProLiant، اعتبارسنجی سازگاری سخت‌افزاری و ثبت درخواست پیش‌فاکتور.

> وضعیت فعلی: پروژه با PHP، Vanilla JavaScript، CSS لوکال و MySQL/MariaDB اجرا می‌شود. Tailwind CDN و وابستگی‌های runtime خارجی UI حذف شده‌اند. اتصال دیتابیس و AI از فایل‌های تنظیمات امن یا متغیرهای محیطی خوانده می‌شود.

---

## فهرست

- [معرفی محصول](#معرفی-محصول)
- [قابلیت‌های اصلی](#قابلیتهای-اصلی)
- [جریان‌های کاربری](#جریانهای-کاربری)
- [معماری فنی](#معماری-فنی)
- [ساختار فایل‌ها](#ساختار-فایلها)
- [مدل داده و دیتابیس](#مدل-داده-و-دیتابیس)
- [APIها](#apiها)
- [دستیار هوشمند AI](#دستیار-هوشمند-ai)
- [منطق اعتبارسنجی](#منطق-اعتبارسنجی)
- [راه‌اندازی و استقرار](#راهاندازی-و-استقرار)
- [فونت و فایل‌های لوکال](#فونت-و-فایلهای-لوکال)
- [نکات امنیتی و عملیاتی](#نکات-امنیتی-و-عملیاتی)
- [چک‌لیست تست قبل از انتشار](#چکلیست-تست-قبل-از-انتشار)
- [محدودیت‌ها و مسیر توسعه](#محدودیتها-و-مسیر-توسعه)

---

## معرفی محصول

کاربر می‌تواند با دو مسیر اصلی کانفیگ سرور را بسازد:

1. **مسیر راهنمایی**: کاربر به سؤال‌های ساده درباره سرویس‌ها، تعداد کاربران، سطح عملکرد، توسعه آینده، Storage و زیرساخت پاسخ می‌دهد. سیستم دقیقاً سه پیشنهاد آماده اقتصادی، مدیریت‌شده و پیشرفته را از دیتابیس انتخاب می‌کند.
2. **مسیر حرفه‌ای**: کاربر قطعات را مرحله‌به‌مرحله انتخاب می‌کند: شاسی، CPU، RAM، Storage، RAID Controller، HBA، GPU، Network Adapter، Riser، Optical Drive و PSU.

در پایان، سیستم سازگاری قطعات را بررسی می‌کند، خلاصه کانفیگ را نشان می‌دهد و درخواست پیش‌فاکتور را با کد رهگیری ثبت می‌کند.

---

## قابلیت‌های اصلی

- رابط کاربری فارسی، RTL و بدون وابستگی runtime به CDN.
- دو مسیر کاربری: راهنمایی و حرفه‌ای.
- پیشنهاد دقیقاً سه سرور آماده از جدول `Prepared_Server_Offers`.
- نمایش وضعیت موجودی، تعداد موجود و زمان تأمین برای پیشنهادهای آماده.
- انتخاب سرور آماده و تبدیل آن به ساختار واقعی `currentConfig` قابل ویرایش.
- دریافت مرحله‌ای قطعات سازگار بر اساس شاسی.
- محاسبه زنده:
  - مجموع Core
  - مجموع RAM
  - ظرفیت قابل استفاده Storage با توجه به RAID
  - مصرف تقریبی برق
  - نیاز به فن High Performance
  - وضعیت PSU
  - وضعیت Backplane / Drive Bay
  - وضعیت PCIe / Riser
- اعتبارسنجی دو لایه در فرانت‌اند و بک‌اند.
- ذخیره Draft و کانفیگ‌های تکمیل‌شده در `localStorage` مرورگر.
- ثبت نهایی در جدول `User_Configurations` با کد رهگیری `HPE-XXXXXX`.
- پیش‌فاکتور قابل چاپ با `window.print()`.
- دستیار هوشمند AI داخل Modal با Context محدود، history چت، quick replies و actionهای قابل اعمال روی کانفیگ.
- فایل یکپارچه local برای رمزها و کلیدها: `config/secrets.local.php`.

---

## جریان‌های کاربری

### مسیر راهنمایی

```text
صفحه شروع
  ↓
پرسش‌های نیازسنجی
  ↓
محاسبه Target فنی
  ↓
دریافت دقیقاً سه پیشنهاد آماده از API
  ↓
نمایش اقتصادی / مدیریت‌شده / پیشرفته
  ↓
مشاهده جزئیات سرور پیشنهادی
  ↓
امکان ویرایش همان کانفیگ در مسیر حرفه‌ای
  ↓
ثبت درخواست پیش‌فاکتور
```

### مسیر حرفه‌ای

```text
صفحه شروع
  ↓
اهداف اولیه اختیاری: Core / RAM / Storage / GPU
  ↓
انتخاب شاسی
  ↓
Load قطعات سازگار با شاسی
  ↓
انتخاب CPU و RAM
  ↓
انتخاب Storage و RAID Controller
  ↓
انتخاب HBA و GPU
  ↓
انتخاب Network Adapter و Riser
  ↓
انتخاب Optical Drive و PSU
  ↓
بررسی نهایی و چاپ خلاصه
  ↓
ثبت درخواست پیش‌فاکتور
```

### دستیار هوشمند در هر مرحله

دکمه «راهنمایی هوشمند» در صفحات اصلی کانفیگ در دسترس است. هنگام کلیک، مرحله فعلی، انتخاب‌های کاربر، هشدارهای Validator و سیگنال‌های محدود دیتابیس به AI ارسال می‌شود تا پاسخ مرحله‌محور بدهد.

---

## معماری فنی

```text
Browser
  │
  │ HTML + CSS Local + Vanilla JavaScript
  ▼
index.php
  │
  ├── assets/style.css
  ├── assets/js/security.js
  ├── assets/main.js
  │     ├── state
  │     ├── sessionManager
  │     ├── api client
  │     ├── validator
  │     ├── wizard / proWizard
  │     ├── configurator
  │     ├── smartAssistant
  │     └── invoice renderer
  │
  ├── api/get_data.php
  ├── api/recommend_servers.php
  ├── api/submit_config.php
  └── api/ai_chat.php
        │
        ├── config/database.php
        ├── config/ai.php
        └── MySQL/MariaDB + AI Provider
```

### تکنولوژی‌ها

| لایه | تکنولوژی |
|---|---|
| UI | HTML + CSS لوکال در `assets/style.css` |
| Client Logic | Vanilla JavaScript |
| Security Helpers | `assets/js/security.js` |
| Backend | PHP با PDO و cURL |
| Database | MySQL / MariaDB |
| AI | سرویس OpenAI-compatible از طریق `api/ai_chat.php` |
| State مرورگر | `localStorage` و `sessionStorage` |

---

## ساختار فایل‌ها

```text
.
├── README.md
├── AGENTS.md
├── DECISIONS.md
├── .gitignore
├── index.php
├── api/
│   ├── get_data.php
│   ├── recommend_servers.php
│   ├── submit_config.php
│   └── ai_chat.php
├── assets/
│   ├── main.js
│   ├── style.css
│   ├── js/
│   │   └── security.js
│   ├── falnic-logo.svg
│   └── hpe-logo.svg
├── config/
│   ├── database.php
│   ├── database.local.example.php
│   ├── ai.php
│   ├── ai.local.example.php
│   └── secrets.local.example.php
├── database/
│   └── migrations/
│       └── 2026_09_06_create_prepared_server_offers.sql
└── falnicc1_server_configurator.sql
```

### فایل‌های local که نباید وارد Git شوند

```text
.env
config/database.local.php
config/ai.local.php
config/secrets.local.php
```

---

## مدل داده و دیتابیس

فایل `falnicc1_server_configurator.sql` اسکیمای کامل و داده‌های اولیه را دارد.

| جدول | کاربرد |
|---|---|
| `Chassis` | شاسی، نسل، فرم‌فاکتور، سوکت، RAM، PCIe، Storage Rules |
| `CPUs` | پردازنده‌ها، Core، TDP، سوکت و سرعت RAM پشتیبانی‌شده |
| `RAMs` | ماژول‌های RAM، ظرفیت، نسل، سرعت و سازگاری CPU |
| `Storage_Drives` | HDD/SSD/NVMe، ظرفیت، فرم‌فاکتور، Interface و توان |
| `Storage_Controllers` | کنترلرهای RAID، فرم‌فاکتور، تعداد Drive و Interface |
| `GPUs` | کارت‌های گرافیک، VRAM، TDP و Slot مصرفی |
| `Network_Adapters` | کارت‌های شبکه Standup و FlexibleLOM |
| `Risers` | رایزرهای PCIe و تعداد Slotها |
| `HBAs` | کارت‌های اتصال به Storage خارجی |
| `Optical_Drives` | درایوهای نوری |
| `Power_Supplies` | پاورها، Wattage، Efficiency و ولتاژ ورودی |
| `Prepared_Server_Offers` | سرورهای آماده برای پیشنهاد اقتصادی/مدیریت‌شده/پیشرفته |
| `User_Configurations` | کانفیگ‌های ثبت‌شده و کد رهگیری |

### Prepared Server Offers

جدول `Prepared_Server_Offers` شامل سه Seed اصلی است:

1. `DL360 Gen9 اقتصادی`
2. `DL380 Gen10 مدیریت‌شده`
3. `ML110 Gen11 پیشرفته`

فیلدهای مهم:

| فیلد | توضیح |
|---|---|
| `selected_components` | JSON قطعات انتخاب‌شده برای تبدیل به `currentConfig` |
| `bullets` | نکات کوتاه نمایشی |
| `stock_status` | `Available`, `Limited`, `Unavailable` |
| `stock_qty` | تعداد موجود |
| `lead_time_days` | زمان تأمین تقریبی |
| `performance_score` | امتیاز کلی برای مرتب‌سازی |
| `generation_rank` | رتبه نسل |
| `expansion_score` | امتیاز توسعه‌پذیری |

### قرارداد سازگاری

- `compatible_chassis_ids = NULL` یا `[]`: سازگار با همه شاسی‌ها.
- آرایه JSON مثل `[1, 3]`: فقط سازگار با همان شاسی‌ها.
- CPU باید `socket_type` برابر `cpu_socket_type` شاسی داشته باشد.
- RAM باید `memory_generation` برابر `ram_generation` شاسی داشته باشد.
- RAM در صورت داشتن `compatible_cpu_ids` باید با CPU انتخابی هم سازگار باشد.

---

## APIها

### `GET /api/get_data.php`

بدون `chassis_id` فقط لیست شاسی‌ها را برمی‌گرداند:

```http
GET /api/get_data.php
```

با `chassis_id` قطعات سازگار همان شاسی را برمی‌گرداند:

```http
GET /api/get_data.php?chassis_id=1
```

خروجی کلی:

```json
{
  "status": "success",
  "data": {
    "chassis": [],
    "cpus": [],
    "rams": [],
    "drives": [],
    "psus": [],
    "controllers": [],
    "gpus": [],
    "networks": [],
    "risers": [],
    "hbas": [],
    "optical_drives": []
  }
}
```

### `POST /api/recommend_servers.php`

ورودی مسیر راهنمایی و Target فعلی را می‌گیرد و دقیقاً سه پیشنهاد آماده برمی‌گرداند.

```json
{
  "target": { "cores": 16, "ram": 64, "storage": 2000, "gpu": false },
  "answers": { "1": ["db"], "2": ["2"], "3": ["med"] }
}
```

خروجی شامل:

- `target` نرمال‌شده
- `offers` دقیقاً سه مورد
- `config` قابل استفاده در فرانت‌اند
- `display_rows` برای جزئیات
- `db` محدود برای merge شدن در `state.db`
- وضعیت موجودی و زمان تأمین

### `POST /api/ai_chat.php`

Gateway امن برای سرویس AI. پاسخ آماده داخلی حذف شده و endpoint به سرویس OpenAI-compatible وصل می‌شود.

ورودی:

```json
{
  "message": "رم بیشتری می‌خوام",
  "context": {},
  "history": []
}
```

خروجی موفق:

```json
{
  "status": "success",
  "reply": "پاسخ کوتاه و مرحله‌محور",
  "question": "سؤال بعدی از کاربر",
  "quick_replies": [
    { "label": "بله، اصلاح کن", "message": "بله، اول CPU و رم را اصلاح کن" }
  ],
  "actions": [
    {
      "label": "اعمال اصلاح CPU/RAM",
      "type": "set_cpu_ram",
      "payload": { "cpu_id": 10, "cpu_qty": 2, "ram_id": 20, "ram_qty": 4 }
    }
  ]
}
```

### `POST /api/submit_config.php`

کانفیگ نهایی را دوباره از دیتابیس اعتبارسنجی می‌کند، توان و قیمت را محاسبه می‌کند و در `User_Configurations` ثبت می‌کند.

خروجی موفق:

```json
{
  "status": "success",
  "tracking_code": "HPE-ABC123",
  "total_power": 552,
  "total_price": null,
  "price_incomplete": true
}
```

---

## دستیار هوشمند AI

دستیار هوشمند در `smartAssistant` داخل `assets/main.js` و endpoint `api/ai_chat.php` پیاده‌سازی شده است.

### داده‌هایی که به AI ارسال می‌شود

به AI کل دیتابیس یا کل state خام ارسال نمی‌شود. فقط context محدود و whitelist شده ارسال می‌شود:

- صفحه و مرحله فعلی کاربر؛ مثل انتخاب CPU/RAM یا Storage.
- Target فنی کاربر: Core، RAM، Storage، GPU، Network و Form Factor.
- قطعات انتخاب‌شده با فیلدهای ضروری و غیرمحرمانه.
- خطاها و هشدارهای Validator.
- چند گزینه سازگار از دیتابیس برای شاسی انتخاب‌شده.
- سه پیشنهاد آماده فعال و وضعیت موجودی آن‌ها.
- حداکثر چند پیام اخیر چت.

### کنترل کیفیت پاسخ AI

Prompt سمت سرور از AI می‌خواهد:

- پاسخ کوتاه بدهد.
- جدول Markdown، thinking، داده خام دیتابیس و متن طولانی ندهد.
- فقط JSON معتبر با `reply`, `question`, `quick_replies`, `actions` برگرداند.
- همیشه بر اساس مرحله فعلی پاسخ بدهد.
- در پایان سؤال کوتاه بپرسد.
- اگر تغییر قابل اعمال وجود دارد، action امن پیشنهاد دهد.

### History چت

- پیام‌های چت در `sessionStorage` با کلید `falnic_ai_chat_history` نگهداری می‌شوند.
- با بستن و باز کردن Modal، چت در همان tab ادامه پیدا می‌کند.
- دکمه «گفتگوی جدید» history را پاک می‌کند.

### Quick Replies و Actions

زیر جواب AI دکمه‌های پیشنهادی نمایش داده می‌شود. دو نوع دکمه داریم:

1. **Quick Reply**: پیام آماده را داخل همان چت ارسال می‌کند.
2. **Action Button**: تغییر امن و کنترل‌شده را روی کانفیگ اعمال می‌کند.

Actionهای مجاز:

| نوع Action | کاربرد |
|---|---|
| `set_cpu` | تغییر CPU و تعداد آن |
| `set_ram` | تغییر RAM و تعداد آن |
| `set_cpu_ram` | اصلاح ترکیبی CPU و RAM |
| `set_ram_qty` | تغییر تعداد ماژول RAM فعلی |
| `set_ram_total` | رساندن RAM به ظرفیت کل موردنظر |
| `set_cpu_qty` | تغییر تعداد CPU فعلی |
| `set_psu_qty` | تغییر تعداد PSU |
| `add_drive_raid10` | افزودن Drive با RAID 10 در صورت داشتن `drive_id` معتبر |

اگر کاربر روی گزینه‌ای مثل «بله، اول CPU و رم را اصلاح کن» کلیک کند، سیستم یا action همراه همان دکمه را اعمال می‌کند یا پیام را به AI می‌فرستد و action برگشتی را به‌صورت کنترل‌شده اعمال می‌کند.

---

## منطق اعتبارسنجی

اعتبارسنجی در دو لایه انجام می‌شود:

1. **فرانت‌اند** برای راهنمایی لحظه‌ای.
2. **بک‌اند** برای جلوگیری از ثبت کانفیگ نامعتبر.

### کنترل‌های فرانت‌اند

- انتخاب شاسی، CPU، RAM و PSU.
- تطبیق تعداد CPU با ظرفیت شاسی.
- رسیدن مجموع Core و RAM به Target.
- ظرفیت RAM نسبت به Slotهای فعال و تعداد CPU.
- هشدار تقارن RAM بین پردازنده‌ها.
- هشدار سرعت RAM نسبت به CPU.
- ظرفیت قابل استفاده RAID.
- نیاز RAID پیشرفته به Controller سخت‌افزاری یا SAS Expander.
- محدودیت تعداد Drive نسبت به Bayها و Controller.
- محدودیت FlexibleLOM به یک کارت.
- محدودیت PCIe، GPU و Riser.
- نیاز Riser سوم به CPU دوم.
- توان PSU با حاشیه امن ۲۰٪.

### کنترل‌های بک‌اند

- اعتبار ID قطعات با خواندن مجدد از دیتابیس.
- سازگاری CPU Socket با شاسی.
- سازگاری RAM Generation با شاسی و CPU.
- سازگاری قطعات اختیاری با شاسی.
- اعتبار تعداد CPU/RAM/Drive.
- اعتبار RAID و ظرفیت قابل استفاده Storage.
- کنترل ظرفیت Bay و Controller.
- کنترل Riser سوم، GPU و PCIe.
- کنترل PSU با حاشیه امن.
- محاسبه مجدد توان و قیمت بدون اعتماد به کلاینت.

---

## راه‌اندازی و استقرار

### پیش‌نیازها

- PHP 8.x پیشنهاد می‌شود.
- Extensionهای PHP: `PDO`, `pdo_mysql`, `curl`, `json`.
- MySQL یا MariaDB.
- وب‌سرور Apache/Nginx یا سرور داخلی PHP برای توسعه.

### 1. ساخت دیتابیس

```sql
CREATE DATABASE falnicc1_server_configurator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import اولیه

```bash
mysql --default-character-set=utf8mb4 -u <db_user> -p falnicc1_server_configurator < falnicc1_server_configurator.sql
```

### 3. Migration برای دیتابیس موجود

اگر دیتابیس قبلاً وجود داشته و فقط فایل‌ها را جایگزین می‌کنید، این Migration را اجرا کنید:

```bash
mysql --default-character-set=utf8mb4 -u <db_user> -p falnicc1_server_configurator < database/migrations/2026_09_06_create_prepared_server_offers.sql
```

### 4. تنظیم رمزها و کلیدها

روش پیشنهادی، استفاده از یک فایل local یکپارچه است:

```bash
cp config/secrets.local.example.php config/secrets.local.php
chmod 644 config/secrets.local.php
```

سپس مقادیر واقعی دیتابیس و AI را در `config/secrets.local.php` وارد کنید. این فایل توسط Git ignore می‌شود.

همچنین می‌توانید به جای فایل یکپارچه، از این فایل‌های جدا استفاده کنید:

```text
config/database.local.php
config/ai.local.php
```

یا متغیرهای محیطی زیر را تعریف کنید:

```text
DB_HOST
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
AI_API_URL
AI_API_KEY
AI_MODEL
AI_TIMEOUT
AI_MAX_TOKENS
AI_TEMPERATURE
```

### 5. اجرای توسعه‌ای

```bash
php -S 0.0.0.0:8000
```

سپس صفحه پروژه را از همان میزبان توسعه باز کنید.

### 6. تست سریع روی سرور

```bash
curl -i /api/get_data.php
```

```bash
curl -i -X POST /api/recommend_servers.php \
  -H "Content-Type: application/json" \
  -d '{"target":{"cores":16,"ram":64,"storage":2000,"gpu":false},"answers":{"1":["db"],"2":["2"],"3":["med"]}}'
```

```bash
curl -i -X POST /api/ai_chat.php \
  -H "Content-Type: application/json" \
  -d '{"message":"کانفیگ فعلی را کوتاه بررسی کن","context":{"target":{"cores":16,"ram":64,"storage":2000,"gpu":false},"currentConfig":{}},"history":[]}'
```

> در دستورهای بالا اگر از دامنه واقعی استفاده می‌کنید، مسیر کامل دامنه را قبل از `/api/...` قرار دهید.

---

## فونت و فایل‌های لوکال

- CSS اصلی در `assets/style.css` است.
- helperهای امنیتی JS در `assets/js/security.js` هستند.
- لوگوی فالنیک در `assets/falnic-logo.svg` است.
- لوگوی HPE در `assets/hpe-logo.svg` است.
- فونت اصلی از `assets/falnic-font.woff2` با `@font-face` خوانده می‌شود.
- Tailwind CDN، تصویر خارجی و وابستگی runtime خارجی UI نباید دوباره اضافه شود.

---

## نکات امنیتی و عملیاتی

- Secret واقعی را در Repository، Markdown، Issue، PR یا Log ذخیره نکنید.
- `config/secrets.local.php`, `config/database.local.php`, `config/ai.local.php` و `.env` نباید وارد Git شوند.
- API Key سرویس AI فقط از config local یا Environment خوانده شود.
- داده ارسالی به AI باید محدود، whitelist شده و بدون Secret باشد.
- خروجی‌های JSON با `Content-Type: application/json; charset=utf-8` ارسال می‌شوند.
- داده‌های dynamic در HTML با `assets/js/security.js` escape شوند.
- بک‌اند منبع نهایی اعتبارسنجی است و نباید به محاسبات مرورگر اعتماد کند.
- در Production بهتر است نمایش جزئیات خطاهای داخلی به کاربر محدودتر شود.
- بعد از آپلود JS/CSS جدید، در مرورگر `Ctrl + F5` بزنید تا cache پاک شود.

---

## چک‌لیست تست قبل از انتشار

### تست‌های خط فرمان

```bash
node --check assets/js/security.js
node --check assets/main.js
git diff --check
```

اگر PHP CLI روی سرور در دسترس است:

```bash
php -l api/get_data.php
php -l api/recommend_servers.php
php -l api/submit_config.php
php -l api/ai_chat.php
php -l config/database.php
php -l config/ai.php
```

### تست UI

- صفحه شروع باز شود.
- مسیر حرفه‌ای شروع شود و شاسی‌ها Load شوند.
- با انتخاب شاسی، قطعات سازگار Load شوند.
- Summary با تغییر CPU/RAM/Storage/GPU/PSU آپدیت شود.
- Validatorها خطا و هشدار را درست نشان دهند.
- مسیر راهنمایی تا سه پیشنهاد ادامه پیدا کند.
- هر پیشنهاد صفحه جزئیات را درست باز کند.
- راهنمای هوشمند در هر مرحله مرحله فعلی را بفهمد.
- history چت با بستن و باز کردن Modal حفظ شود.
- Quick Reply پیام را در همان چت ادامه دهد.
- Actionهایی مثل اصلاح CPU/RAM واقعاً روی کانفیگ اعمال شوند.
- ثبت نهایی کد رهگیری تولید کند.

---

## محدودیت‌ها و مسیر توسعه

- قیمت بسیاری از قطعات هنوز `NULL` است؛ در این حالت `total_price=null` و `price_incomplete=true` می‌شود.
- `assets/main.js` هنوز بزرگ است و فقط بخشی از ساختار JS جدا شده؛ ماژولار شدن تدریجی توصیه می‌شود.
- تست خودکار و CI در پروژه تعریف نشده است.
- Ruleهای پیشنهاددهی و AI باید با تجربه فروش و داده واقعی موجودی تکمیل‌تر شوند.
- PDF واقعی قابل آرشیو هنوز پیاده‌سازی نشده و فعلاً از چاپ مرورگر استفاده می‌شود.
- پنل مدیریت قطعات، قیمت‌ها، موجودی و پیشنهادهای آماده هنوز وجود ندارد.

---

## نگهداری مستندات

هر تغییری در رفتار محصول، دیتابیس، API، امنیت یا تصمیم معماری باید همزمان در این فایل‌ها ثبت شود:

- `README.md`: راهنمای محصول، نصب، استقرار و معماری جاری.
- `AGENTS.md`: دستورالعمل عملیاتی توسعه و QA.
- `DECISIONS.md`: تصمیمات معماری و دلیل انتخاب‌ها.
