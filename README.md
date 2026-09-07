# کانفیگوراتور سرور فالنیک — افزونه وردپرس

افزونه وردپرس فارسی و راست‌به‌چپ برای نیازسنجی، پیشنهاد سرور آماده HPE ProLiant، انتخاب قطعات، اعتبارسنجی سازگاری سخت‌افزاری، ثبت پیش‌فاکتور و پنل مدیریت کامل کاتالوگ.

> نسخه فعلی: **1.1.0** · وردپرس ۵.۸+ · PHP ۷.۴+ · CSS/JS کاملاً لوکال (بدون CDN)

---

## فهرست

- [معرفی](#معرفی)
- [قابلیت‌های اصلی](#قابلیتهای-اصلی)
- [نصب و راه‌اندازی](#نصب-و-راهاندازی)
- [ساختار فایل‌ها](#ساختار-فایلها)
- [شورت‌کد و فرانت‌اند](#شورتکد-و-فرانتاند)
- [پنل مدیریت](#پنل-مدیریت)
- [مدل داده](#مدل-داده)
- [APIهای AJAX](#apiهای-ajax)
- [دستیار هوشمند AI](#دستیار-هوشمند-ai)
- [اعتبارسنجی](#اعتبارسنجی)
- [امنیت و عملکرد](#امنیت-و-عملکرد)
- [چک‌لیست تست](#چکلیست-تست)
- [نگهداری مستندات](#نگهداری-مستندات)

---

## معرفی

کاربر با دو مسیر کانفیگ می‌سازد:

1. **مسیر راهنمایی** — پاسخ به سؤال‌های ساده؛ سیستم دقیقاً سه پیشنهاد آماده (اقتصادی / مدیریت‌شده / پیشرفته) از جدول `Prepared_Server_Offers` برمی‌گرداند.
2. **مسیر حرفه‌ای** — انتخاب مرحله‌به‌مرحله: شاسی، CPU، RAM، Storage، RAID، HBA، GPU، Network، Riser، Optical، PSU.

در پایان سازگاری بررسی می‌شود، خلاصه نمایش داده می‌شود و درخواست پیش‌فاکتور با کد رهگیری `HPE-XXXXXX` ثبت می‌گردد.

---

## قابلیت‌های اصلی

- شورت‌کد `[falnic_server_configurator]` — CSS/JS فقط در همان صفحه بارگذاری می‌شود.
- رابط فارسی RTL بدون وابستگی runtime خارجی.
- پیشنهاد پایدار سه‌سطحی با نردبان اقتصادی / مدیریت‌شده / پیشرفته.
- نمایش موجودی، تعداد و زمان تأمین.
- تبدیل پیشنهاد آماده به `currentConfig` قابل ویرایش در مسیر حرفه‌ای.
- محاسبه زنده Core، RAM، Storage قابل استفاده (RAID)، توان، فن، PSU، Backplane، PCIe/Riser.
- اعتبارسنجی دو لایه (فرانت‌اند + بک‌اند).
- Draft در `localStorage` مرورگر.
- دستیار AI با Context محدود، history، Quick Reply و Action امن.
- پنل مدیریت کامل ۱۲ نوع قطعه + پیشنهادهای آماده + صندوق درخواست‌ها.
- ساخت/ترمیم خودکار اسکیما هنگام فعال‌سازی و ارتقا.
- کش قطعات با invalidate خودکار پس از تغییر در پنل.
- نونس وردپرس روی همه endpointها + rate limit برای AI و ثبت.

---

## نصب و راه‌اندازی

### پیش‌نیاز

- WordPress 5.8 یا بالاتر
- PHP 7.4+ (۸.x پیشنهاد می‌شود)
- MySQL / MariaDB با پشتیبانی `utf8mb4`
- Extensionهای PHP: `json`، `curl` (برای AI)

### نصب

1. کل محتوای این مخزن را در مسیر زیر قرار دهید:

```text
wp-content/plugins/falnic-server-configurator/
```

فایل اصلی باید این باشد:

```text
wp-content/plugins/falnic-server-configurator/falnic-server-configurator.php
```

2. از منوی **افزونه‌ها** پلاگین را فعال کنید. در همان لحظه:
   - ۱۳ جدول بررسی/ساخته می‌شوند
   - ستون‌ها و ایندکس‌های ناقص ترمیم می‌شوند
   - جدول‌های خالی کاتالوگ با seed اولیه پر می‌شوند

3. یک برگه بسازید و شورت‌کد را قرار دهید:

```text
[falnic_server_configurator]
```

(نام مستعار legacy: `[hpe_server_configurator]`)

4. از **کانفیگوراتور سرور → تنظیمات** سرویس AI را پیکربندی کنید (اختیاری).

### Import دامپ خارجی (اختیاری)

اگر دامپ کامل کاتالوگ دارید، می‌توانید آن را مستقیم در دیتابیس وردپرس import کنید. نام و ساختار جدول‌ها یکسان است؛ پلاگین داده‌های موجود را تشخیص می‌دهد و دوباره seed نمی‌کند.

---

## ساختار فایل‌ها

```text
falnic-server-configurator/
├── falnic-server-configurator.php   # Bootstrap افزونه
├── uninstall.php                    # حذف option/transient (+ drop جدول با opt-in)
├── readme.txt                       # متادیتای مخزن وردپرس
├── README.md                        # مستند محصول (همین فایل)
├── AGENTS.md                        # راهنمای توسعه و QA
├── DECISIONS.md                     # تصمیمات معماری (ADR)
├── TEST-REPORT.md                   # گزارش تست‌ها
├── .gitignore
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
├── assets/
│   ├── style.css                    # CSS دست‌نویس RTL، ایزوله زیر .falnic-sc-app
│   ├── main.js                      # منطق کلاینت (wizard, validator, AI, …)
│   ├── js/security.js               # escape برای XSS
│   ├── falnic-font.woff2
│   ├── falnic-logo.svg
│   └── hpe-logo.svg
├── includes/
│   ├── class-falnic-sc-tables.php
│   ├── class-falnic-sc-install.php
│   ├── class-falnic-sc-shortcode.php
│   ├── class-falnic-sc-ajax.php
│   ├── class-falnic-sc-ajax-context.php
│   ├── class-falnic-sc-ajax-text.php
│   ├── class-falnic-sc-ai.php
│   ├── class-falnic-sc-admin.php
│   ├── class-falnic-sc-crud.php
│   ├── falnic-sc-schema.php
│   ├── falnic-sc-catalog-defs.php
│   └── falnic-sc-functions.php
├── templates/
│   └── app-shell.php                # Markup اپ (شورت‌کد)
└── seed/
    └── catalog-seed.sql             # داده اولیه کاتالوگ
```

---

## شورت‌کد و فرانت‌اند

### بارگذاری شرطی

- `wp_register_*` در `wp_enqueue_scripts`
- `wp_enqueue_*` فقط هنگام رندر شورت‌کد
- روی بقیه صفحات سایت هیچ assetی لود نمی‌شود

### پیکربندی JS

```js
window.FALNIC_SC_CONFIG = {
  ajaxUrl: '.../admin-ajax.php',
  nonce: '...',
  homeUrl: '...',
  pageUrl: '...',
  aiEnabled: true|false,
  version: '1.1.0'
};
```

### جریان‌های کاربری

**مسیر راهنمایی**

```text
Intro → پرسش‌های نیازسنجی → Target فنی → ۳ پیشنهاد → جزئیات → ویرایش حرفه‌ای (اختیاری) → پیش‌فاکتور
```

**مسیر حرفه‌ای**

```text
Intro → اهداف اختیاری → شاسی → CPU/RAM → Storage/RAID → HBA/GPU → Network/Riser → Optical/PSU → بررسی → ثبت
```

### لوگو

- اگر در **ظاهر → سفارشی‌سازی → لوگو** لوگوی سایت تنظیم شده باشد، همان در سربرگ اپ نمایش داده می‌شود.
- در غیر این صورت لوگوی bundled فالنیک استفاده می‌شود.

---

## پنل مدیریت

منوی **کانفیگوراتور سرور** شامل:

| صفحه | کاربرد |
|---|---|
| پیشخوان | وضعیت جداول، میان‌برها، گزارش نصب |
| درخواست‌های پیش‌فاکتور | صندوق inbox با تغییر وضعیت و حذف |
| شاسی / CPU / RAM / هارد / کنترلر / GPU / شبکه / رایزر / HBA / نوری / پاور | CRUD کامل |
| پیشنهادهای آماده | مدیریت ۳+ آفر با موجودی |
| تنظیمات | AI، rate limit، cache، حذف داده هنگام uninstall |
| ابزارها | بررسی/بازسازی ساختار، seed، purge cache |

دسترسی: `manage_options`

---

## مدل داده

۱۳ جدول با نام فیزیکی یکسان (بدون prefix اجباری وردپرس؛ قابل override با فیلتر `falnic_sc_table_name`):

| جدول | کلید داخلی | کاربرد |
|---|---|---|
| `Chassis` | chassis | شاسی، سوکت، RAM، PCIe، Storage/Cooling rules |
| `CPUs` | cpus | پردازنده، Core، TDP، سوکت |
| `RAMs` | rams | ماژول RAM و سازگاری CPU |
| `Storage_Drives` | drives | HDD/SSD/NVMe |
| `Storage_Controllers` | controllers | کنترلر RAID |
| `GPUs` | gpus | کارت گرافیک |
| `Network_Adapters` | networks | Standup / FlexibleLOM |
| `Risers` | risers | رایزر PCIe |
| `HBAs` | hbas | HBA فیبر |
| `Optical_Drives` | optical_drives | درایو نوری |
| `Power_Supplies` | psus | پاور |
| `Prepared_Server_Offers` | offers | سرور آماده + موجودی |
| `User_Configurations` | requests | درخواست‌های ثبت‌شده |

### قرارداد سازگاری

- `compatible_chassis_ids = NULL` یا `[]` → همه شاسی‌ها
- آرایه JSON مثل `[1, 3]` → فقط همان شاسی‌ها
- CPU با `cpu_socket_type` شاسی
- RAM با `ram_generation` شاسی و در صورت وجود `compatible_cpu_ids`

### پیشنهادهای آماده (seed)

1. DL360 Gen9 اقتصادی
2. DL380 Gen10 مدیریت‌شده
3. ML110 Gen11 پیشرفته

فیلدهای مهم: `selected_components`, `bullets`, `stock_status`, `stock_qty`, `lead_time_days`, `performance_score`, `generation_rank`, `expansion_score`

---

## APIهای AJAX

همه از `admin-ajax.php` با نونس `falnic_sc_public`:

| Action | معادل منطقی | ورودی |
|---|---|---|
| `falnic_sc_get_data` | لیست شاسی / قطعات سازگار | `chassis_id` اختیاری |
| `falnic_sc_recommend` | سه پیشنهاد آماده | JSON: `target`, `answers` |
| `falnic_sc_submit` | اعتبارسنجی + ثبت | JSON کانفیگ کامل |
| `falnic_sc_ai_chat` | Gateway AI | JSON: `message`, `context`, `history` |

Payload معمولاً در `$_POST['payload']` به‌صورت JSON string ارسال می‌شود.

### خروجی نمونه submit

```json
{
  "success": true,
  "data": {
    "status": "success",
    "tracking_code": "HPE-ABC123",
    "total_power": 552,
    "total_price": null,
    "price_incomplete": true
  }
}
```

---

## دستیار هوشمند AI

- تنظیمات از **تنظیمات افزونه** (endpoint، API key، model، timeout، tokens، temperature)
- فقط وقتی `ai_endpoint` و `ai_api_key` پر باشند فعال است (`aiEnabled` در فرانت)
- Context whitelist‌شده: مرحله، target، قطعات منتخب، validator، گزینه‌های سازگار، ۳ offer، history کوتاه
- پاسخ ساختاریافته: `reply`, `question`, `quick_replies`, `actions`
- History در `sessionStorage` با کلید `falnic_ai_chat_history`

### Actionهای مجاز

```text
set_cpu, set_ram, set_cpu_ram, set_ram_qty, set_ram_total,
set_cpu_qty, set_psu_qty, add_drive_raid10
```

---

## اعتبارسنجی

### فرانت‌اند (`validator` در main.js)

- شاسی / CPU / RAM / PSU الزامی
- ظرفیت سوکت و slot
- Target Core/RAM
- تقارن و سرعت RAM
- RAID و ظرفیت قابل استفاده
- Bay / Controller
- FlexibleLOM (حداکثر ۱)
- PCIe / GPU / Riser (رایزر سوم نیاز به CPU دوم)
- توان PSU با حاشیه ۲۰٪

### بک‌اند (`Falnic_SC_Ajax::submit_config`)

- خواندن مجدد IDها از DB
- همان Ruleها بدون اعتماد به کلاینت
- محاسبه مجدد توان و قیمت

### RAID — ظرفیت قابل استفاده

| RAID | فرمول تقریبی |
|---|---|
| None/JBOD | `qty × capacity` |
| RAID 1 | `capacity` (۲ دیسک) |
| RAID 5 | `(qty − 1) × capacity` |
| RAID 6 | `(qty − 2) × capacity` |
| RAID 10 | `(qty / 2) × capacity` |
| RAID 50 | `(qty − 2) × capacity` |
| RAID 60 | `(qty − 4) × capacity` |

---

## امنیت و عملکرد

- Secret AI فقط در option تنظیمات وردپرس (نه در Git)
- نونس روی همه AJAXهای عمومی
- Rate limit جدا برای AI و submit
- Escape دینامیک با `security.js` (`h()`, `attr()`, `inlineJson()`)
- CSS ایزوله زیر `.falnic-sc-app` با `isolation: isolate` و `!important` برای مقاومت در برابر قالب
- Cache پاسخ `get_data` با TTL قابل تنظیم؛ purge پس از CRUD
- Uninstall به‌صورت پیش‌فرض جدول‌ها را نگه می‌دارد؛ حذف کامل فقط با opt-in در تنظیمات

---

## چک‌لیست تست

### Syntax

```bash
# از ریشه افزونه
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
node --check assets/js/security.js
node --check assets/main.js
node --check admin/js/admin.js
```

### دستی / smoke

- [ ] فعال‌سازی روی DB خالی → ۱۳ جدول + seed
- [ ] شورت‌کد فقط در همان صفحه asset لود می‌کند
- [ ] مسیر حرفه‌ای: شاسی → قطعات → Summary
- [ ] مسیر راهنمایی: ۳ پیشنهاد پایدار (eco/managed/advanced)
- [ ] Smart Assistant: history، quick reply، action
- [ ] ثبت → کد `HPE-…`
- [ ] CRUD هر کاتالوگ در ادمین
- [ ] تغییر وضعیت درخواست
- [ ] ابزار «بررسی و بازسازی ساختار»
- [ ] غیرفعال‌سازی قالب سنگین روی استایل دکمه‌ها اثر مخرب نگذارد

گزارش کامل تست‌های خودکار در `TEST-REPORT.md` است.

---

## نگهداری مستندات

| فایل | نقش |
|---|---|
| `README.md` | محصول، نصب، معماری جاری |
| `AGENTS.md` | قرارداد توسعه و QA |
| `DECISIONS.md` | ADR و دلیل تصمیم‌ها |
| `readme.txt` | متادیتای مخزن وردپرس / changelog |
| `TEST-REPORT.md` | نتیجه تست‌ها |

هر تغییر رفتار، اسکیما، AJAX، امنیت یا UI باید همزمان docs مرتبط را به‌روز کند. Secret واقعی هرگز در Markdown نوشته نشود.
