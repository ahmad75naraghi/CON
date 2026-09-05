<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کانفیگوراتور حرفه‌ای سرور HPE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            background-color: #f8fafc;
        }

        .tooltip-icon {
            cursor: help;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
            position: relative;
            top: -2px;
        }

        .tooltip-container {
            position: relative;
            display: inline-block;
        }

        .tooltip-text {
            visibility: hidden;
            width: max-content;
            max-width: 350px;
            background-color: #1f2937;
            color: #fff;
            text-align: right;
            border-radius: 8px;
            padding: 10px;
            position: absolute;
            z-index: 50;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: normal;
            line-height: 1.6;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }

        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #success-modal,
            #success-modal * {
                visibility: visible;
            }

            #success-modal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
            }

            .no-print {
                display: none !important;
            }

            /* مخفی کردن بک‌گراند تیره مودال در نسخه چاپ */
            .bg-opacity-50 {
                background-opacity: 0;
                background: transparent;
            }
        }
    </style>
</head>

<body class="text-gray-800 pb-20">

    <!-- بخش انتخاب مسیر (Intro) -->
    <div id="view-intro" class="container mx-auto p-4 max-w-3xl mt-12 transition-all duration-500">
        <div class="text-center mb-10">
            <div class="flex justify-center items-center gap-2 mb-6">
                <span class="text-xl font-bold text-blue-900">فالنیک</span>
                <span class="text-sm text-gray-500">(ایران اچ پی)</span>
                <!-- لوگو فالنیک در اینجا قرار گیرد -->
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">سرورتون رو چطور میسازیم؟</h1>
            <p class="text-gray-600 text-sm mb-4">بسته به اینکه چقدر با مشخصات فنی سرور آشنایید، یکی از دو مسیر زیر رو انتخاب کنید.</p>
            <p class="text-yellow-600 text-xs font-bold flex items-center justify-center gap-1">
                <span>💡</span> هر وقت خواستید میتونید بینشون جابجا بشید.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <!-- مسیر راهنمایی -->
            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 relative hover:shadow-md transition">
                <div class="absolute top-6 left-6 w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white text-xl shadow-lg">💡</div>
                <h3 class="text-teal-600 font-bold mb-4">مسیر راهنمایی</h3>
                <h2 class="text-lg font-bold text-gray-900 mb-6 leading-relaxed">چند سوال ساده میپرسیم و بر اساس نیازتون، بهترین ترکیب رو پیشنهاد میدیم.</h2>
                <button onclick="wizard.initGuidance()" class="w-full sm:w-auto px-6 py-2 border-2 border-blue-900 text-blue-900 font-bold rounded-lg hover:bg-blue-900 hover:text-white transition mb-4">شروع راهنمایی</button>
                <p class="text-xs text-gray-600 flex items-center gap-1"><span class="text-green-500 text-base">☑</span> مناسب کسایی که مطمئن نیستن دقیقاً چی نیاز دارن</p>
            </div>

            <!-- مسیر حرفه ای -->
            <div class="bg-orange-50 p-6 rounded-2xl border border-orange-100 relative hover:shadow-md transition">
                <div class="absolute top-6 left-6 w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white text-xl shadow-lg">⚙️</div>
                <h3 class="text-orange-500 font-bold mb-4">مسیر حرفه ای</h3>
                <h2 class="text-lg font-bold text-gray-900 mb-6 leading-relaxed">CPU، رم، فضای ذخیره سازی و بقیه قطعات رو خودتون قدم به قدم انتخاب میکنید.</h2>
                <button onclick="wizard.showView('view-pro')" class="w-full sm:w-auto px-6 py-2 border-2 border-blue-900 text-blue-900 font-bold rounded-lg hover:bg-blue-900 hover:text-white transition mb-4">شروع کانفیگ</button>
                <p class="text-xs text-gray-600 flex items-center gap-1"><span class="text-green-500 text-base">☑</span> برای IT منیجرها و متخصصین سخت افزار</p>
            </div>
        </div>

        <!-- کانفیگ های ذخیره شده -->
        <div class="mt-12 space-y-8">
            <!-- کانفیگ‌های ناتمام (Drafts) -->
            <div id="draft-sessions-container" class="hidden">
                <h3 class="text-sm font-bold text-gray-500 mb-3 flex items-center gap-2">⏱️ کانفیگ‌های ناتمام</h3>
                <div id="draft-sessions-list" class="space-y-2"></div>
            </div>

            <!-- کانفیگ‌های ثبت شده (Completed) -->
            <div id="completed-sessions-container" class="hidden">
                <h3 class="text-sm font-bold text-gray-500 mb-3 flex items-center gap-2">✅ کانفیگ‌های نهایی شده</h3>
                <div id="completed-sessions-list" class="space-y-2"></div>
            </div>
        </div>
    </div>
    <!-- مسیر تعیین اهداف اولیه حرفه‌ای (Pro Goals) -->
    <div id="view-pro" class="hidden container mx-auto p-4 max-w-4xl mt-8">
        <div class="flex justify-between items-center mb-10">
            <span class="bg-purple-600 text-white px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-1">✨ راهنمایی هوشمند</span>
            <div class="flex items-center gap-4">
                <span class="text-orange-500 font-bold flex items-center gap-2">⚙️ مسیر حرفه ای</span>
                <button onclick="wizard.showView('view-intro')" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-2">قبل از شروع، هدفتون از این سرور چیه؟</h2>
        <p class="text-sm text-gray-500 mb-8">پر کردن حتی یکی از این سوالات کافیه تا فقط قطعات مرتبط رو بهتون پیشنهاد بدیم. هر وقت خواستید میتونید رد بشید.</p>

        <div class="bg-white rounded-xl divide-y divide-gray-100 mb-6 border border-gray-200 shadow-sm">
            <details class="group p-4" open>
                <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800 outline-none">
                    <span>حداقل هسته پردازنده (CPU) <span class="block text-xs text-gray-400 font-normal mt-1">یک گزینه را انتخاب کنید.</span></span>
                    <span class="transition group-open:rotate-180 text-blue-500">↓</span>
                </summary>
                <div class="mt-4 text-sm">
                    <input type="number" id="req-cores" value="16" min="1" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-200 outline-none transition">
                </div>
            </details>

            <details class="group p-4">
                <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800 outline-none">
                    <span>حداقل رم (Ram) <span class="block text-xs text-gray-400 font-normal mt-1">یک گزینه را انتخاب کنید.</span></span>
                    <span class="transition group-open:rotate-180 text-blue-500">↓</span>
                </summary>
                <div class="mt-4 text-sm">
                    <input type="number" id="req-ram" value="64" min="1" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-200 outline-none transition">
                </div>
            </details>

            <details class="group p-4">
                <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800 outline-none">
                    <span>فضای ذخیره سازی <span class="block text-xs text-gray-400 font-normal mt-1">یک گزینه را انتخاب کنید.</span></span>
                    <span class="transition group-open:rotate-180 text-blue-500">↓</span>
                </summary>
                <div class="mt-4 text-sm">
                    <input type="number" id="req-storage-tb" value="2" min="1" step="0.5" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="مقدار به ترابایت">
                </div>
            </details>

            <details class="group p-4">
                <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800 outline-none">
                    <span>نیاز به گرافیک <span class="block text-xs text-gray-400 font-normal mt-1">برای رندر، AI یا مجازی سازی گرافیکی نیاز است.</span></span>
                    <span class="transition group-open:rotate-180 text-blue-500">↓</span>
                </summary>
                <div class="mt-4 text-sm">
                    <select id="req-gpu-pro" class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-200 outline-none transition">
                        <option value="no">خیر</option>
                        <option value="yes">بله</option>
                    </select>
                </div>
            </details>
        </div>

        <div class="flex justify-end gap-3">
            <button onclick="wizard.skipAndStart()" class="px-6 py-3 border-2 border-blue-900 text-blue-900 font-bold rounded-lg hover:bg-blue-50 transition">رد کردن</button>
            <button onclick="wizard.submit('pro')" class="px-6 py-3 bg-gray-200 text-gray-500 font-bold rounded-lg cursor-not-allowed transition" id="pro-submit-btn" disabled>تایید و شروع کانفیگ ←</button>
        </div>
    </div>
    <!-- مسیر حرفه‌ای (Pro) -->
    <!-- جایگزین کامل #config-section در فایل index.php -->
    <div id="view-pro-configurator" class="hidden container mx-auto p-4 max-w-6xl mt-8">

        <!-- هدر و نوار پیشرفت چندمرحله‌ای -->
        <div class="flex justify-between items-center mb-8">
            <span class="bg-purple-600 text-white px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-1">✨ راهنمایی هوشمند</span>
            <div class="flex items-center gap-4">
                <span class="text-orange-500 font-bold flex items-center gap-2">⚙️ مسیر حرفه ای</span>
                <button onclick="wizard.showView('view-pro')" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
            </div>
        </div>

        <!-- ProgressBar حرفه‌ای -->
        <div class="flex justify-between items-center mb-10 relative text-sm font-bold" id="pro-progress-bar">
            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-gray-200 -z-10"></div>
            <!-- توسط جاوااسکریپت مقداردهی می‌شود -->
        </div>

        <div class="flex flex-col md:flex-row gap-6">

            <!-- سایدبار خلاصه سرور (Live Summary) -->
            <div class="w-full md:w-1/3 h-fit sticky top-4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 border-b pb-2">خلاصه سرور شما</h3>
                    <ul class="space-y-4 text-sm divide-y divide-gray-100">
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">هسته‌های پردازشی</span>
                            <span id="summary-cores" class="font-bold text-gray-900">0</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">حجم کل رم</span>
                            <span id="summary-ram" class="font-bold text-gray-900">0 GB</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">رید کنترلر</span>
                            <span id="summary-raid" class="font-bold text-gray-900 text-left line-clamp-1 max-w-[150px]" dir="ltr">پیش‌فرض شاسی</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">کارت گرافیک</span>
                            <span id="summary-gpu" class="font-bold text-gray-900 text-left line-clamp-1 max-w-[150px]" dir="ltr">ندارد</span>
                        </li>
                        <li class="flex justify-between items-center pt-2 bg-red-50 p-2 rounded -mx-2">
                            <span class="text-gray-800 border-r-4 border-red-500 pr-2">ظرفیت هارد <span class="text-xs text-gray-500">(Usable)</span></span>
                            <div class="text-left">
                                <span id="summary-storage" class="font-bold text-gray-900">0 TB</span>
                            </div>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">بک‌پلین افزوده شده</span>
                            <span id="summary-backplane" class="font-bold text-gray-900">خیر (فقط پیش‌فرض)</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">درایو نوری</span>
                            <span id="summary-optical" class="font-bold text-gray-900">ندارد</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">رایزرهای اضافی</span>
                            <span id="summary-risers" class="font-bold text-gray-900">0 عدد اضافه شده</span>
                        </li>
                        <li class="flex justify-between items-center pt-2 bg-green-50 p-2 rounded -mx-2">
                            <span class="text-gray-800 border-r-4 border-green-500 pr-2">وضعیت فن</span>
                            <span id="summary-fan" class="font-bold text-green-700">Standard</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">توان کل محاسبه شده</span>
                            <span id="summary-power" class="font-bold text-gray-900" dir="ltr">0 W</span>
                        </li>
                        <li class="flex justify-between items-center pt-2">
                            <span class="text-gray-500">پاور انتخاب شده</span>
                            <span id="summary-psu" class="font-bold text-gray-900 text-left line-clamp-1 max-w-[150px]">انتخاب نشده</span>
                        </li>
                    </ul>
                    <button id="pro-save-btn" onclick="proWizard.submitConfig()" class="w-full mt-6 border-2 border-blue-900 text-blue-900 hover:bg-blue-50 font-bold py-3 rounded-lg transition flex items-center justify-center gap-2">
                        <span>ذخیره کانفیگ</span> <span>💾</span>
                    </button>
                </div>
            </div>

            <!-- محتوای مراحل -->
            <div class="w-full md:w-2/3">
                <div id="pro-step-header" class="mb-6 border-b pb-4">
                    <h2 id="pro-step-title" class="text-2xl font-bold text-gray-900 mb-2">انتخاب شاسی</h2>
                    <p id="pro-step-desc" class="text-sm text-gray-500">پایه و اساس سرور خود را بر اساس معماری مدنظر مشخص کنید.</p>
                </div>

                <div id="pro-step-content" class="bg-white rounded-xl divide-y divide-gray-100 border border-gray-200 shadow-sm">

                    <!-- مرحله ۱: شاسی -->
                    <div id="pro-step-1" class="pro-step-container">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> شاسی (Chassis) <span class="block text-xs text-gray-400 font-normal mt-1">یک گزینه را انتخاب کنید.</span></span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <select id="chassis-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 outline-none focus:ring-2 focus:ring-blue-200" onchange="configurator.handleChassisChange()">
                                    <option value="">در حال دریافت اطلاعات...</option>
                                </select>
                                <div id="validator-chassis" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>
                    </div>

                    <!-- مرحله ۲: پردازش و رم -->
                    <div id="pro-step-2" class="pro-step-container hidden">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> پردازنده (CPU)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8 flex gap-4">
                                <div class="w-3/4">
                                    <select id="cpu-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handleCpuChange()">
                                        <option value="">ابتدا شاسی را انتخاب کنید</option>
                                    </select>
                                </div>
                                <div class="w-1/4">
                                    <select id="cpu-qty" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handleCpuChange()">
                                        <option value="1">۱ عدد</option>
                                        <option value="2">۲ عدد</option>
                                    </select>
                                </div>
                            </div>
                            <div id="validator-cpu" class="mt-3 ml-8 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                        </details>

                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> حافظه رم (RAM)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8 flex gap-4">
                                <div class="w-3/4">
                                    <select id="ram-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handleRamChange()">
                                        <option value="">ابتدا پردازنده را انتخاب کنید</option>
                                    </select>
                                </div>
                                <div class="w-1/4 relative">
                                    <span class="absolute -top-5 right-1 text-xs text-gray-500 font-normal">تعداد ماژول</span>
                                    <input type="number" id="ram-qty" value="1" min="1" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled oninput="if(parseInt(this.value) > parseInt(this.max)){this.value=this.max;} configurator.handleRamChange()">
                                </div>
                            </div>
                            <div id="validator-ram" class="mt-3 ml-8 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                        </details>
                    </div>

                    <!-- مرحله ۳: ذخیره‌سازی و کنترلر -->
                    <div id="pro-step-3" class="pro-step-container hidden">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> فضای ذخیره سازی (Storage)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <button onclick="configurator.addDriveRow()" id="add-drive-btn" class="mb-3 bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-100 disabled:opacity-50 border border-blue-200 transition" disabled>➕ افزودن هارد جدید</button>
                                <div id="drives-container" class="space-y-3"></div>
                                <div id="validator-storage" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>

                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> کنترلر رید (RAID Controller)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <div id="default-controller-display" class="text-sm text-blue-800 bg-blue-50 border border-blue-200 p-3 rounded-lg mb-3 hidden"></div>
                                <select id="controller-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePCIeChange()">
                                    <option value="">استفاده از کنترلر پیش‌فرض مادربرد</option>
                                </select>
                                <div class="mt-3 flex items-center p-3 bg-gray-50 rounded-lg border">
                                    <input type="checkbox" id="sas-expander-checkbox" class="ml-2 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="configurator.handlePCIeChange()" disabled>
                                    <label for="sas-expander-checkbox" class="text-sm text-gray-700">افزودن کارت SAS Expander (اشغال ۱ اسلات PCIe x8)</label>
                                </div>
                                <div id="validator-raid-controller" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>
                    </div>

                    <!-- مرحله ۴: HBA و گرافیک -->
                    <div id="pro-step-4" class="pro-step-container hidden">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> کارت‌های HBA (Host Bus Adapter)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <button onclick="configurator.addHbaRow()" id="add-hba-btn" class="mb-3 bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-100 disabled:opacity-50 border border-blue-200 transition" disabled>➕ افزودن کارت HBA</button>
                                <div id="hbas-container" class="space-y-3"></div>
                                <div id="validator-hba" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>

                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> کارت گرافیک (GPU)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8 flex gap-4">
                                <div class="w-3/4">
                                    <select id="gpu-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePCIeChange()">
                                        <option value="">بدون گرافیک (پیش‌فرض)</option>
                                    </select>
                                </div>
                                <div class="w-1/4 hidden" id="gpu-qty-container">
                                    <select id="gpu-qty" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePCIeChange()">
                                        <option value="1">۱ عدد</option>
                                        <option value="2">۲ عدد</option>
                                    </select>
                                </div>
                            </div>
                            <div id="validator-gpu" class="mt-3 ml-8 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                        </details>
                    </div>

                    <!-- مرحله ۵: شبکه و رایزر -->
                    <div id="pro-step-5" class="pro-step-container hidden">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> کارت شبکه (Network Adapters)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <div id="default-network-display" class="text-sm text-blue-800 bg-blue-50 border border-blue-200 p-3 rounded-lg mb-3 hidden"></div>
                                <button onclick="configurator.addNetworkRow()" id="add-network-btn" class="mb-3 bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-100 disabled:opacity-50 border border-blue-200 transition" disabled>➕ افزودن کارت شبکه جانبی</button>
                                <div id="networks-container" class="space-y-3"></div>
                                <div id="validator-network" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>

                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> رایزرهای توسعه (PCIe Risers)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8 space-y-4">
                                <div class="text-sm text-gray-700 bg-gray-50 border p-3 rounded-lg">
                                    <span class="text-green-500 font-bold">✔</span> <b>رایزر اول (پیش‌فرض روی مادربرد):</b> مدل 8-16-8 نصب است.
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">رایزر دوم (Secondary)</label>
                                    <select id="riser2-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePCIeChange()">
                                        <option value="">بدون رایزر دوم</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">رایزر سوم (Tertiary)</label>
                                    <select id="riser3-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePCIeChange()">
                                        <option value="">بدون رایزر سوم</option>
                                    </select>
                                    <p id="riser3-warning" class="text-xs font-bold text-orange-600 mt-2 hidden">⚠️ رایزر سوم نیازمند پردازنده دوم است!</p>
                                </div>
                            </div>
                            <div id="validator-risers" class="mt-3 ml-8 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                        </details>
                    </div>

                    <!-- مرحله ۶: تغذیه و جانبی -->
                    <div id="pro-step-6" class="pro-step-container hidden">
                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> درایو نوری (Optical Drive / DVD)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <button onclick="configurator.addOpticalRow()" id="add-optical-btn" class="mb-3 bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-100 disabled:opacity-50 border border-blue-200 transition" disabled>➕ افزودن درایو نوری</button>
                                <div id="opticals-container" class="space-y-3"></div>
                                <div id="validator-optical-drive" class="mt-3 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                            </div>
                        </details>

                        <details class="group p-4" open>
                            <summary class="flex justify-between items-center cursor-pointer list-none font-bold text-gray-800">
                                <span class="flex items-center gap-2"><span class="text-blue-500 text-lg">💬</span> منبع تغذیه (Power Supply)</span>
                                <span class="transition group-open:rotate-180">↓</span>
                            </summary>
                            <div class="mt-4 pl-8">
                                <p class="text-xs text-gray-500 mb-2">تعداد پیش‌فرض و ثابت: ۲ عدد (Redundant)</p>
                                <select id="psu-select" class="w-full border border-gray-300 p-3 rounded-lg bg-gray-50 disabled:bg-gray-200 outline-none" disabled onchange="configurator.handlePsuChange()">
                                    <option value="">در حال محاسبه توان مورد نیاز...</option>
                                </select>
                                <p id="psu-calc-display" class="text-sm font-bold text-blue-700 mt-3 p-3 bg-blue-50 rounded-lg hidden"></p>
                            </div>
                            <div id="validator-Power" class="mt-3 ml-8 text-sm bg-gray-50 border rounded p-3 space-y-1"></div>
                        </details>
                    </div>

                </div>

                <!-- دکمه‌های ناوبری پویا -->
                <div class="flex justify-between mt-8 pt-4 border-t border-gray-100">
                    <button id="pro-prev-btn" onclick="proWizard.prev()" class="px-8 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 transition shadow-sm hidden">← مرحله قبل</button>
                    <div class="flex-1 flex justify-end gap-3">
                        <button id="pro-next-btn" onclick="proWizard.next()" class="px-8 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 transition shadow-sm">مرحله بعد ←</button>
                        <button id="pro-submit-final-btn" onclick="proWizard.submitConfig()" class="hidden px-8 py-3 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition shadow-lg ring-4 ring-blue-100">بررسی نهایی کانفیگ ✔</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- صفحه نتیجه نهایی مسیر حرفه‌ای (Invoice) -->
    <div id="view-pro-results" class="hidden container mx-auto p-4 max-w-4xl mt-8">
        <div class="flex justify-between items-center mb-8">
            <span class="bg-purple-600 text-white px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-1">✨ راهنمایی هوشمند</span>
            <button onclick="wizard.showView('view-pro-configurator')" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
        </div>

        <div class="text-right mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">کانفیگ سرور شما آماده ست!</h2>
            <p class="text-sm text-gray-500">همه قطعات انتخابی با هم سازگار هستن. میتونید همین الان درخواست پیش فاکتور بدید یا ویرایش کنید.</p>
        </div>

        <div class="bg-gray-50 rounded-xl border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <span class="font-bold text-gray-800">کد کانفیگ: SVR - <span id="pro-generated-svr-code">----</span></span>
            </div>

            <!-- از همان ساختار جدول قبلی برای رندر استفاده می‌شود -->
            <div class="divide-y divide-gray-200 text-sm" id="pro-details-table-body">
                <!-- ردیف‌ها توسط جاوااسکریپت تزریق می‌شوند -->
            </div>

            <div class="mt-6 flex justify-end">
                <button onclick="window.print()" class="flex items-center gap-2 border border-orange-500 text-orange-500 px-4 py-2 rounded font-bold hover:bg-orange-50 transition">خلاصه کانفیگ PDF 📥</button>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 items-center">
            <span class="text-xs text-gray-500 flex items-center gap-1">✉ پیش فاکتور تا ۲۴ ساعت کاری براتون آماده و ارسال میشه. تعهدی برای خرید وجود نداره.</span>
            <button onclick="wizard.showView('view-pro-configurator')" class="px-6 py-3 border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 flex items-center gap-2 transition">✎ ویرایش سرور</button>
            <button onclick="configurator.submitFinal()" class="px-6 py-3 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition shadow-md">درخواست پیش فاکتور</button>
        </div>
    </div>

    <!-- مسیر راهنمایی (Guidance) -->
    <!-- مسیر راهنمایی (Guidance Wizard) -->
    <div id="view-guidance" class="hidden container mx-auto p-4 max-w-4xl mt-8">
        <div class="flex justify-between items-center mb-8">
            <span class="bg-purple-600 text-white px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-1">✨ راهنمایی هوشمند</span>
            <div class="flex items-center gap-4">
                <span class="text-teal-600 font-bold flex items-center gap-2">💡 مسیر راهنمایی</span>
                <button onclick="wizard.prevStep()" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
            </div>
        </div>

        <!-- نوار پیشرفت داینامیک -->
        <div class="flex justify-between items-center mb-12 text-sm font-bold text-gray-300 relative" id="guidance-progress">
            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-gray-200 -z-10"></div>
            <!-- توسط JS پر می‌شود -->
        </div>

        <!-- هدر پویا -->
        <h2 id="guidance-title" class="text-2xl font-bold text-gray-900 mb-2"></h2>
        <p id="guidance-subtitle" class="text-sm text-gray-500 mb-8"></p>

        <!-- محتوای مراحل (توسط JS مدیریت می‌شود) -->
        <div id="guidance-content" class="bg-white rounded-xl border border-gray-200 p-6 mb-6"></div>

        <div class="flex justify-between">
            <button id="guidance-prev-btn" onclick="wizard.prevStep()" class="px-8 py-3 border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 transition hidden">← سوال قبل</button>
            <button id="guidance-next-btn" onclick="wizard.nextStep()" class="px-8 py-3 bg-gray-200 text-gray-500 font-bold rounded-lg cursor-not-allowed transition" disabled>سوال بعد ←</button>
        </div>
    </div>

    <!-- صفحه پیشنهادات (Offers) -->
    <div id="view-offers" class="hidden container mx-auto p-4 max-w-5xl mt-8">
        <div class="flex justify-between items-center mb-8">
            <button onclick="wizard.showView('view-guidance')" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
        </div>

        <div class="text-center mb-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">پیشنهادات ما بر اساس پاسخ های شما</h2>
            <p class="text-sm text-gray-500">میتونید هر کدوم رو بپسندید یا نپسندید تا پیشنهادهای بهتری بگیرید، یا مستقیم یکی رو انتخاب کنید.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <!-- اقتصادی -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between hover:shadow-lg transition">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">اقتصادی</h3>
                        <span class="text-2xl">🖨️</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-6 line-clamp-3">برای شروع کار یا تیم های کوچک، بدون هزینه اضافه روی امکاناتی که فعلا بهش نیاز ندارید.</p>
                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> برای تا ۵۰ کاربر همزمان بدون افت سرعت</li>
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> امکان نصب مستقیم سرویس‌ها بدون محدودیت</li>
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> قابل ارتقا در آینده، بدون نیاز به تعویض</li>
                    </ul>
                </div>
                <button onclick="wizard.selectOffer('eco')" class="w-full py-3 border-2 border-blue-900 text-blue-900 font-bold rounded-lg hover:bg-blue-50 transition">جزییات سرور</button>
            </div>

            <!-- مدیریت شده -->
            <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6 flex flex-col justify-between transform scale-105 shadow-md">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-blue-900">مدیریت شده</h3>
                        <span class="text-2xl">🏢</span>
                    </div>
                    <p class="text-sm text-gray-700 mb-6">بهترین تعادل بین قیمت و عملکرد برای نیاز شما. نه کمبود منابع دارید، نه پول اضافه می‌دید برای چیزی که استفاده نمی‌کنید.</p>
                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-2 text-sm text-gray-800"><span class="text-green-600 text-base">✔</span> تا ۲۰۰ کاربر همزمان رو بدون کندی پشتیبانی میکنه</li>
                        <li class="flex items-start gap-2 text-sm text-gray-800"><span class="text-green-600 text-base">✔</span> فضای خالی برای توسعه ۳ سال آینده در نظر گرفته شده</li>
                        <li class="flex items-start gap-2 text-sm text-gray-800"><span class="text-green-600 text-base">✔</span> زمان پاسخ‌دهی سریع‌تر برای سرویس‌هایی مثل دیتابیس</li>
                    </ul>
                </div>
                <button onclick="wizard.selectOffer('managed')" class="w-full py-3 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition shadow-lg">جزییات سرور</button>
            </div>

            <!-- پیشرفته -->
            <div class="bg-white border border-gray-200 rounded-2xl p-6 flex flex-col justify-between hover:shadow-lg transition">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">پیشرفته</h3>
                        <span class="text-2xl">🚀</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-6">اگه می‌خواید یه بار برای همیشه خیالتون از محدودیت راحت باشه و آماده رشد سریع سازمان باشید.</p>
                    <ul class="space-y-3 mb-6">
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> ظرفیتی که حتی با رشد چندبرابری کاربران نیاز به ارتقا نداره</li>
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> عملکرد کافی برای اجرای همزمان چند سرویس سنگین</li>
                        <li class="flex items-start gap-2 text-sm text-gray-700"><span class="text-green-500 text-base">✔</span> زیرساخت آماده برای مجازی‌سازی یا افزودن سرویس‌های جدید</li>
                    </ul>
                </div>
                <button onclick="wizard.selectOffer('pro')" class="w-full py-3 border-2 border-blue-900 text-blue-900 font-bold rounded-lg hover:bg-blue-50 transition">جزییات سرور</button>
            </div>
        </div>

        <div class="flex justify-between items-center border-t pt-6">
            <div class="flex items-center gap-4 text-gray-500 text-sm">
                <span>بازخورد شما به پیشنهادات:</span>
                <button class="hover:text-gray-800">👍</button>
                <button class="hover:text-gray-800">👎</button>
            </div>
            <button onclick="wizard.launchConfigurator()" class="px-6 py-2 border border-blue-900 text-blue-900 rounded-lg hover:bg-gray-50 transition font-bold">انتخاب دستی قطعات</button>
        </div>
    </div>

    <!-- صفحه جزییات سرور (Server Details) -->
    <div id="view-server-details" class="hidden container mx-auto p-4 max-w-4xl mt-8">
        <div class="flex justify-between items-center mb-8">
            <span class="bg-purple-600 text-white px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-1">✨ راهنمایی هوشمند</span>
            <button onclick="wizard.showView('view-offers')" class="text-blue-900 text-sm font-bold flex items-center gap-1 hover:text-blue-700">بازگشت →</button>
        </div>

        <div class="text-right mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">سرور پیشنهادی</h2>
            <p class="text-sm text-gray-500">بهترین تعادل بین قیمت و عملکرد برای نیاز شما. نه کمبود منابع دارید، نه پول اضافه می‌دید برای چیزی که استفاده نمی‌کنید.</p>
        </div>

        <div class="bg-gray-50 rounded-xl border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <span class="font-bold text-gray-800">کد کانفیگ: SVR - <span id="generated-svr-code">2841</span></span>
            </div>

            <div class="divide-y divide-gray-200 text-sm" id="details-table-body">
                <!-- ردیف‌ها توسط جاوااسکریپت تزریق می‌شوند -->
            </div>

            <div class="mt-6 flex justify-end">
                <button class="flex items-center gap-2 border border-orange-500 text-orange-500 px-4 py-2 rounded font-bold hover:bg-orange-50">خلاصه کانفیگ PDF 📥</button>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 items-center">
            <span class="text-xs text-gray-500 flex items-center gap-1">✉ پیش فاکتور تا ۲۴ ساعت کاری براتون آماده و ارسال میشه. تعهدی برای خرید وجود نداره.</span>
            <button onclick="wizard.launchConfigurator()" class="px-6 py-3 border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50 flex items-center gap-2">✎ ویرایش سرور</button>
            <button onclick="configurator.submitFinal()" class="px-6 py-3 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition">درخواست پیش فاکتور</button>
        </div>
    </div>

    <!-- مودال موفقیت نهایی -->
    <div id="modal-final-success" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center relative transform transition-all">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-green-600 text-3xl">✔</span>
            </div>
            <p class="text-gray-800 font-bold mb-6">پیش فاکتور شما با موفقیت ثبت شد و تا ۲۴ ساعت دیگر برای شما ارسال میشود.</p>
            <button onclick="document.getElementById('modal-final-success').classList.add('hidden')" class="w-full bg-blue-900 text-white font-bold py-2 rounded-lg hover:bg-blue-800">متوجه شدم</button>
        </div>
    </div>

    <div id="success-modal" class="hidden fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full my-8">
            <div class="p-8 border-b-8 border-blue-700">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800">پیش‌فاکتور سرور HPE</h2>
                        <p class="text-gray-500 mt-1">شماره پیگیری: <span id="tracking-code-display" class="font-mono font-bold text-blue-600"></span></p>
                        <p class="text-gray-500">تاریخ: <span id="invoice-date"></span></p>
                    </div>
                    <div class="text-left">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/4/46/Hewlett_Packard_Enterprise_logo.svg" alt="HPE Logo" class="h-10 opacity-80">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right border-collapse">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border p-3 w-10 text-center">ردیف</th>
                                <th class="border p-3">شرح قطعه (Component)</th>
                                <th class="border p-3 w-20 text-center">تعداد</th>
                                <th class="border p-3 w-1/3">توضیحات فنی</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-tbody" class="text-gray-800">
                            <!-- ردیف‌ها توسط جاوااسکریپت پر می‌شوند -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center bg-gray-50 p-4 border rounded">
                    <div>
                        <p class="font-bold text-gray-700">توان مصرفی محاسبه شده (TDP): <span id="invoice-power" class="text-red-600">0 W</span></p>
                    </div>
                    <div class="text-left text-xs text-gray-500">
                        * این پیش‌فاکتور به صورت سیستمی تولید شده و فاقد اعتبار مالیاتی است.<br>
                        * تمامی قطعات بر اساس استانداردهای HPE پیکربندی شده‌اند.
                    </div>
                </div>
                <div id="invoice-alerts-container" class="mt-6 bg-gray-50 border border-gray-200 p-4 rounded hidden">
                    <h4 class="font-bold text-gray-800 mb-3 border-b border-gray-300 pb-2">اخطارها و ملاحظات سیستم:</h4>
                    <div id="invoice-alerts" class="space-y-2"></div>
                </div>
                <div class="mt-6 flex justify-end gap-4 no-print">
                    <button onclick="document.getElementById('success-modal').classList.add('hidden')" class="px-6 py-2 border border-gray-300 rounded hover:bg-gray-100 font-bold">بستن</button>
                    <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold flex items-center gap-2">
                        🖨️ چاپ / دانلود PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/main.js?v=<?= time() ?>"></script>
</body>

</html>