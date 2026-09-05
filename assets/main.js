// assets/main.js

const state = {
    sessionId: null,
    activeMode: 'pro', // 'pro' | 'guidance'
    db: null,
    target: {},
    currentConfig: {
        chassis: null, cpu: null, cpuQty: 1, ram: null, ramQty: 1,
        drives: [], opticalDrives: [], controller: null, hbas: [],
        gpu: null, gpuQty: 0, networks: [], psu: null, psuQty: 1,
        riser2: null, riser3: null,
        sasExpander: false,
        totalWatts: 0, reqHighPerfFan: false
    }
};

// ==========================================
// 1. Session Manager (ذخیره و بازیابی کانفیگ‌ها)
// ==========================================
const sessionManager = {
    storageKey: 'falnic_server_sessions',
    
    init: () => {
        if (!state.sessionId) state.sessionId = 'session_' + Date.now();
        sessionManager.renderLists();
    },

    getAll: () => JSON.parse(localStorage.getItem(sessionManager.storageKey) || '[]'),

    save: (status = 'draft', trackingCode = null) => {
        if (!state.currentConfig.chassis && Object.keys(wizard.answers).length === 0 && status === 'draft') return; // ذخیره نکن اگر کاملا خالیه

        const sessions = sessionManager.getAll();
        const existingIndex = sessions.findIndex(s => s.id === state.sessionId);
        
        const sessionData = {
            id: state.sessionId,
            timestamp: Date.now(),
            status: status,
            trackingCode: trackingCode,
            stateDump: {
                activeMode: state.activeMode,
                target: state.target,
                currentConfig: state.currentConfig,
                answers: wizard.answers,
                proStep: typeof proWizard !== 'undefined' ? proWizard.currentStep : 1,
                guidanceStep: wizard.currentStepIndex
            }
        };

        if (existingIndex > -1) sessions[existingIndex] = sessionData;
        else sessions.push(sessionData);

        localStorage.setItem(sessionManager.storageKey, JSON.stringify(sessions));
        sessionManager.renderLists();
    },

    renderLists: () => {
        const sessions = sessionManager.getAll().sort((a, b) => b.timestamp - a.timestamp);
        const drafts = sessions.filter(s => s.status === 'draft');
        const completed = sessions.filter(s => s.status === 'completed');

        const renderItems = (items, targetId, isCompleted) => {
            const listEl = document.getElementById(targetId);
            const containerEl = document.getElementById(targetId.replace('-list', '-container'));
            if (!listEl || !containerEl) return;

            if (items.length === 0) {
                containerEl.classList.add('hidden');
                return;
            }

            containerEl.classList.remove('hidden');
            listEl.innerHTML = items.map(item => {
                const date = new Date(item.timestamp).toLocaleDateString('fa-IR', { hour: '2-digit', minute: '2-digit' });
                const chassisName = item.stateDump.currentConfig?.chassis?.model || 'در حال نیازسنجی...';
                const actionText = isCompleted ? 'مشاهده پیش‌فاکتور ←' : 'ادامه کانفیگ ←';
                const actionClass = isCompleted ? 'text-green-600' : 'text-blue-900';
                const modeName = item.stateDump.activeMode === 'pro' ? 'مسیر حرفه‌ای' : 'مسیر راهنمایی';
                
                return `
                <button onclick="sessionManager.load('${item.id}')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:shadow-md text-gray-800 py-4 px-6 rounded-xl flex justify-between items-center transition text-right">
                    <div>
                        <div class="font-bold text-sm text-gray-800">${isCompleted ? `کد رهگیری: ${item.trackingCode}` : chassisName}</div>
                        <div class="text-xs text-gray-400 mt-1">${modeName} | بروزرسانی: ${date}</div>
                    </div>
                    <span class="font-bold text-sm ${actionClass}">${actionText}</span>
                </button>`;
            }).join('');
        };

        renderItems(drafts, 'draft-sessions-list', false);
        renderItems(completed, 'completed-sessions-list', true);
    },

    load: async (id) => {
        const session = sessionManager.getAll().find(s => s.id === id);
        if (!session) return;

        // تزریق دیتای ذخیره شده به State جاری
        state.sessionId = session.id;
        state.activeMode = session.stateDump.activeMode;
        state.target = session.stateDump.target;
        state.currentConfig = session.stateDump.currentConfig;
        wizard.answers = session.stateDump.answers;

        if (session.status === 'completed') {
            if (!state.db) await api.fetchData();
            if (state.currentConfig.chassis && !state.db.drives) await api.fetchChassisData(state.currentConfig.chassis.id);
            uiRenderer.generateInvoice(state.currentConfig, session.trackingCode);
            return;
        }

        // Hydration برای فرم‌های ناتمام
        if (state.activeMode === 'guidance') {
            wizard.currentStepIndex = session.stateDump.guidanceStep;
            wizard.showView('view-guidance');
            wizard.renderStep();
        } else {
            const btn = document.activeElement;
            const originalText = btn ? btn.innerText : '';
            if (btn && btn.tagName === 'BUTTON') btn.innerText = "در حال بازیابی...";
            
            await sessionManager.hydrateProConfig(session);
            
            if (btn && btn.tagName === 'BUTTON') btn.innerText = originalText;
        }
    },

    hydrateProConfig: async (session) => {
        if (!state.db) await api.fetchData();

        const config = state.currentConfig;
        if (config.chassis) {
            document.getElementById('chassis-select').value = config.chassis.id;
            await api.fetchChassisData(config.chassis.id);
            configurator.populateChassisSpecificDropdowns();

            // فعال‌سازی المان‌ها
            ['cpu-select', 'cpu-qty', 'add-drive-btn', 'add-optical-btn', 'controller-select', 'sas-expander-checkbox', 'add-hba-btn', 'gpu-select', 'add-network-btn', 'psu-select', 'riser2-select', 'riser3-select'].forEach(el => {
                const element = document.getElementById(el);
                if (element) element.disabled = false;
            });

            // بازسازی لیست پردازنده‌ها
            const cpuSelect = document.getElementById('cpu-select');
            if (cpuSelect) {
                cpuSelect.innerHTML = '<option value="">پردازنده را انتخاب کنید...</option>';
                state.db.cpus.forEach(c => {
                    if (c.cores * config.chassis.max_cpus >= (state.target.cores || 0)) {
                        cpuSelect.innerHTML += `<option value="${c.id}">${c.model_name} (${c.cores} Cores)</option>`;
                    }
                });
                if (config.cpu) cpuSelect.value = config.cpu.id;
            }

            const cpuQty = document.getElementById('cpu-qty');
            if (cpuQty && config.cpu) cpuQty.value = config.cpuQty;

            // بازسازی لیست رم‌ها
            const ramSelect = document.getElementById('ram-select');
            const ramQtyInput = document.getElementById('ram-qty');
            if (ramSelect && config.cpu) {
                ramSelect.disabled = false;
                ramSelect.innerHTML = '<option value="">رم را انتخاب کنید...</option>';
                const maxRam = Math.min(config.chassis.max_ram_slots || 24, config.cpuQty * (config.chassis.ram_slots_per_cpu || 12));
                if (ramQtyInput) {
                    ramQtyInput.disabled = false;
                    ramQtyInput.max = maxRam;
                }

                state.db.rams.forEach(r => {
                    const cpuOk = !r.compatible_cpu_ids || r.compatible_cpu_ids.length === 0 || r.compatible_cpu_ids.includes(config.cpu.id);
                    if (cpuOk && (r.capacity_gb * maxRam) >= (state.target.ram || 0)) {
                        ramSelect.innerHTML += `<option value="${r.id}">${r.model_name}</option>`;
                    }
                });
                if (config.ram) ramSelect.value = config.ram.id;
            }
            if (ramQtyInput && config.ram) ramQtyInput.value = config.ramQty;

            // تنظیم مقادیر دراپ‌داون‌های ساده
            const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = val; };
            setVal('controller-select', config.controller ? config.controller.id : '');
            
            const sasEl = document.getElementById('sas-expander-checkbox');
            if(sasEl) { sasEl.checked = config.sasExpander; sasEl.disabled = false; }

            setVal('gpu-select', config.gpu ? config.gpu.id : '');
            if (config.gpu) {
                const gpuQtyContainer = document.getElementById('gpu-qty-container');
                if(gpuQtyContainer) gpuQtyContainer.classList.remove('hidden');
                const gpuQtyEl = document.getElementById('gpu-qty');
                if(gpuQtyEl) { gpuQtyEl.disabled = false; gpuQtyEl.value = config.gpuQty; }
            }

            setVal('psu-select', config.psu ? config.psu.id : '');
            setVal('riser2-select', config.riser2 ? config.riser2.id : '');
            setVal('riser3-select', config.riser3 ? config.riser3.id : '');
        }

        // رندر مجدد آرایه‌های داینامیک
        configurator.renderDrives();
        configurator.renderNetworks();
        configurator.renderHbas();
        configurator.renderOpticals();

        wizard.showView('view-pro-configurator');
        proWizard.currentStep = session.stateDump.proStep || 1;
        proWizard.render();
        configurator.calculateSummary(); 
    }
};

// ==========================================
// 2. UI Renderer (تولید پیش‌فاکتور نهایی)
// ==========================================
const uiRenderer = {
    generateInvoice: (config, trackingCode, date = new Date().toLocaleDateString('fa-IR')) => {
        document.getElementById('tracking-code-display').innerText = trackingCode;
        document.getElementById('invoice-date').innerText = date;
        document.getElementById('invoice-power').innerText = `${config.totalWatts || 0} Watts`;

        const tbody = document.getElementById('invoice-tbody');
        tbody.innerHTML = '';
        let rowIndex = 1;

        const addRow = (name, qty, desc) => {
            tbody.innerHTML += `
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="border p-2 text-center font-bold">${rowIndex++}</td>
                    <td class="border p-2 font-semibold text-blue-900">${name}</td>
                    <td class="border p-2 text-center font-mono">${qty}</td>
                    <td class="border p-2 text-xs text-gray-600">${desc}</td>
                </tr>`;
        };

        if(config.chassis) addRow(`HPE ProLiant ${config.chassis.model} ${config.chassis.generation}`, 1, `P/N: ${config.chassis.part_number} | FF: ${config.chassis.form_factor}`);
        if(config.cpu) addRow(config.cpu.model_name, config.cpuQty, `Cores: ${config.cpu.cores} | Base Freq: ${config.cpu.base_frequency_ghz}GHz | P/N: ${config.cpu.part_number}`);
        if(config.ram) addRow(config.ram.model_name, config.ramQty, `Type: ${config.ram.ram_type} | Speed: ${config.ram.speed_mt}MT/s | P/N: ${config.ram.part_number}`);

        config.drives.forEach(d => {
            const driveObj = state.db.drives.find(x => x.id == d.driveId);
            if (driveObj) addRow(driveObj.model_name, d.qty, `Interface: ${driveObj.interface} | RAID: ${d.raid !== 'none' ? d.raid : 'JBOD'} | P/N: ${driveObj.part_number}`);
        });

        config.opticalDrives.forEach(o => {
            const optObj = state.db.optical_drives.find(x => x.id == o.opticalId);
            if (optObj) addRow(optObj.model_name, 1, `P/N: ${optObj.part_number}`);
        });

        if(config.chassis) {
            let activeController = config.controller ? config.controller.model_name : config.chassis.default_controller;
            let ctrlDesc = config.controller ? `P/N: ${config.controller.part_number} | Hardware RAID` : `Integrated On-Board Controller`;
            addRow(`RAID Controller: ${activeController}`, 1, ctrlDesc);
        }

        if (config.sasExpander) addRow('HPE SAS Expander Card', 1, 'Expands storage capacity beyond base controller limits');

        config.networks.forEach(n => {
            const netObj = state.db.networks.find(x => x.id == n.networkId);
            if (netObj) addRow(netObj.model_name, n.qty, `Ports: ${netObj.port_count} | Speed: ${netObj.speed_gbps}Gbps | P/N: ${netObj.part_number}`);
        });

        config.hbas.forEach(h => {
            const hbaObj = state.db.hbas.find(x => x.id == h.hbaId);
            if (hbaObj) addRow(hbaObj.model_name, h.qty, `P/N: ${hbaObj.part_number}`);
        });

        if (config.gpu && config.gpuQty > 0) {
            addRow(config.gpu.model_name, config.gpuQty, `VRAM: ${config.gpu.memory_gb}GB | P/N: ${config.gpu.part_number}`);
        }

        if (config.riser2) addRow(`PCIe Riser (Secondary) - ${config.riser2.model_name}`, 1, `x16 Slots: ${config.riser2.x16_slots}`);
        if (config.riser3) addRow(`PCIe Riser (Tertiary) - ${config.riser3.model_name}`, 1, `x16 Slots: ${config.riser3.x16_slots}`);
        if (config.psu) addRow(config.psu.model_name, config.psuQty, `Efficiency: ${config.psu.efficiency} | Hot-Plug: Yes | P/N: ${config.psu.part_number}`);

        // تزریق اخطارهای زرد رنگ
        const alertsContainer = document.getElementById('invoice-alerts-container');
        const alertsDiv = document.getElementById('invoice-alerts');
        if(alertsContainer && alertsDiv) {
            alertsDiv.innerHTML = '';
            let hasAlerts = false;
            document.querySelectorAll('[id^="validator-"] > div').forEach(div => {
                if (div.innerText.includes('⚠️')) {
                    hasAlerts = true;
                    alertsDiv.innerHTML += `<div class="text-sm text-gray-700 flex items-start gap-2"><span class="mt-1 text-yellow-600 font-bold">⚠️</span> <span>${div.innerText.replace('⚠️', '').trim()}</span></div>`;
                }
            });
            hasAlerts ? alertsContainer.classList.remove('hidden') : alertsContainer.classList.add('hidden');
        }

        document.getElementById('success-modal').classList.remove('hidden');
    }
};

// ==========================================
// 3. پیکربندی داده‌های مسیر راهنمایی
// ==========================================
const guidanceConfig = [
    {
        id: 1, title: 'چه سرویس ها و نرم افزارهایی قراره روی این سرور نصب بشه؟', sub: 'نوع نرم افزار مستقیم روی انتخاب پردازنده و رم اثر میذاره.', type: 'checkbox', tip: 'میتوانید چند گزینه انتخاب کنید.',
        options: [
            { val: 'accounting', label: 'نرم‌افزار حسابداری/مالی' }, { val: 'crm', label: 'اتوماسیون و CRM' },
            { val: 'web', label: 'میزبانی وب‌سایت / پورتال' }, { val: 'db', label: 'دیتابیس سنگین (SQL/Oracle)' },
            { val: 'ai', label: 'هوش مصنوعی / یادگیری ماشین' }, { val: 'virt', label: 'مجازی‌سازی (ESXi/Proxmox)' },
            { val: 'storage', label: 'فایل سرور / آرشیو اطلاعات' }, { val: 'ad', label: 'اکتیو دایرکتوری / DNS' }
        ]
    },
    {
        id: 2, title: 'تعداد کاربران استفاده‌کننده از سرویس‌ها چقدر است؟', sub: 'تعداد کاربران مستقیم روی انتخاب پردازنده و رم اثر میذاره.', type: 'radio',
        options: [
            { val: '1', label: 'تا ۵۰ کاربر', desc: 'سطح پایه' }, { val: '2', label: '۵۰ تا ۲۰۰ کاربر', desc: 'سطح متوسط' },
            { val: '4', label: '۲۰۰ تا ۱۰۰۰ کاربر', desc: 'سطح کلان' }, { val: '8', label: 'بیش از ۱۰۰۰ کاربر', desc: 'سطح Enterprise' }
        ]
    },
    {
        id: 3, title: 'چه سطحی از عملکرد مدنظر شماست؟', sub: 'سطح عملکرد روی معماری سخت‌افزار تاثیر مستقیم دارد.', type: 'radio',
        options: [
            { val: 'min', label: 'حداقل', desc: 'پایین ترین سطح منابع - حداقل هزینه' },
            { val: 'med', label: 'متوسط', desc: 'سطح منابع استاندارد - هزینه مدیریت شده' },
            { val: 'max', label: 'حداکثر', desc: 'بالاترین سطح - هزینه بالاتر' }
        ]
    },
    {
        id: 4, title: 'تا ۳ سال آینده، قصد توسعه یا ارتقای این سرور رو دارید؟', sub: 'اگه جوابتون "بله"ست، شاسی و منبع تغذیه رو با فضای خالی برای توسعه پیشنهاد میدیم.', type: 'radio',
        options: [
            { val: 'yes', label: 'بله قطعا ارتقا میدهیم', desc: 'نیاز به پلتفرم مقیاس پذیر' },
            { val: 'no', label: 'خیر', desc: 'کانفیگ بسته و نهایی است' }
        ]
    },
    {
        id: 5, title: 'آیا ذخیره سازی مجزا (Storage) دارید؟', sub: 'آیا ذخیره سازی (استوریج) مجزا دارید؟', type: 'radio',
        options: [
            { val: 'yes', label: 'بله، اطلاعات روی Storage جداگانه است', desc: '' },
            { val: 'no', label: 'خیر، تمام اطلاعات روی همین سرور ذخیره میشود', desc: '' }
        ]
    },
    {
        id: 6, title: 'زیرساخت فعلی سازمانتون چطوریه؟', sub: 'نیازی نیست دقیق باشه. کافیه بگید همین الان چه تجهیزاتی دارید و چطور به هم وصلن.', type: 'checkbox', tip: 'میتوانید چند گزینه انتخاب کنید.',
        options: [
            { val: 'fiber', label: 'کابل فیبر نوری' }, { val: 'lan', label: 'شبکه LAN (مس)' },
            { val: 'serverroom', label: 'اتاق سرور مجزا' }, { val: 'rack', label: 'رک استاندارد' }
        ]
    }
];

function calculatePcieUsage(config) {
    let avail_x16_gpu = 0;
    let avail_general = (config.chassis?.base_x8_slots || 0) + (config.chassis?.base_x16_slots || 0);

    if (config.riser2) {
        avail_x16_gpu += config.riser2.x16_slots;
        avail_general += (config.riser2.total_slots - config.riser2.x16_slots);
    }
    if (config.riser3 && config.cpuQty >= 2) {
        avail_x16_gpu += config.riser3.x16_slots;
        avail_general += (config.riser3.total_slots - config.riser3.x16_slots);
    }

    let req_gpu = config.gpuQty || 0;
    let req_general = 0;

    if (config.sasExpander) req_general += 1;
    if (config.controller && config.controller.pcie_slots_used > 0) req_general += config.controller.pcie_slots_used;

    config.hbas.forEach(h => {
        let hbaObj = state.db?.hbas?.find(x => x.id == h.hbaId);
        if (hbaObj && hbaObj.pcie_slots_used > 0) req_general += (hbaObj.pcie_slots_used * (parseInt(h.qty) || 1));
    });

    config.networks.forEach(n => {
        let netObj = state.db?.networks?.find(x => x.id == n.networkId);
        if (netObj && netObj.form_factor !== 'FlexibleLOM' && netObj.pcie_slots_used > 0) {
            req_general += (netObj.pcie_slots_used * (parseInt(n.qty) || 1));
        }
    });

    if (config.gpu) req_general += ((config.gpu.pcie_slots_used - 1) * req_gpu);

    const remaining_gpu_slots = Math.max(0, avail_x16_gpu - req_gpu);
    const total_avail_general = avail_general + remaining_gpu_slots;

    return { avail_x16_gpu, avail_general, req_gpu, req_general, remaining_gpu_slots, total_avail_general };
}

const api = {
    fetchData: async () => {
        try {
            const response = await fetch('api/get_data.php');
            const result = await response.json();
            if (result.status === 'success') {
                state.db = result.data;
                configurator.initDropdowns();
            } else { alert("خطا در دریافت اطلاعات از دیتابیس!"); }
        } catch (error) { console.error("Fetch Error:", error); }
    },
    fetchChassisData: async (chassisId) => {
        try {
            const response = await fetch(`api/get_data.php?chassis_id=${encodeURIComponent(chassisId)}`);
            const result = await response.json();
            if (result.status === 'success') {
                const fullChassisList = state.db.chassis;
                state.db = { ...state.db, ...result.data, chassis: fullChassisList };
                return true;
            } else {
                alert("خطا در دریافت قطعات سازگار با این شاسی!");
                return false;
            }
        } catch (error) {
            console.error("Fetch Error:", error);
            alert("خطا در ارتباط با سرور!");
            return false;
        }
    }
};

const validator = {
    renderItem: (condition, msgSuccess, msgWait, msgError, highlightFields = []) => {
        let statusText = '', icon = '', textColor = '';
        if (condition === true) {
            statusText = msgSuccess; icon = '✅'; textColor = 'text-green-600 font-bold';
            validator.removeHighlight(highlightFields);
        } else if (condition === false) {
            statusText = msgError; icon = '❌'; textColor = 'text-red-500 font-bold';
            validator.addHighlight(highlightFields);
        } else if (condition === 'warning') {
            statusText = msgError; icon = '⚠️'; textColor = 'text-yellow-600 font-bold';
            validator.removeHighlight(highlightFields);
        } else {
            statusText = msgWait; icon = '⬜'; textColor = 'text-gray-400';
            validator.removeHighlight(highlightFields);
        }
        return `<div class="flex items-start gap-2 ${textColor} mb-1.5"><span class="text-base leading-none mt-1">${icon}</span> <span class="text-sm">${statusText}</span></div>`;
    },

    addHighlight: (fields) => {
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.classList.add('border-red-500', 'ring-2', 'ring-red-100'); el.classList.remove('border-gray-200', 'border-gray-300'); }
        });
    },

    removeHighlight: (fields) => {
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.classList.remove('border-red-500', 'ring-2', 'ring-red-100'); el.classList.add('border-gray-300'); }
        });
    },

    runChecks: () => {
        const config = state.currentConfig;

        const chBox = document.getElementById('validator-chassis');
        if (chBox) {
            let hasChassis = config.chassis ? true : null;
            let html = validator.renderItem(hasChassis, `شاسی ${config.chassis?.model || ''} با موفقیت انتخاب شد`, 'شاسی سرور بررسی نشده است', '', ['chassis-select']);
            if (state.target.formFactor && state.target.formFactor !== 'any') {
                let ffMatch = config.chassis ? (config.chassis.form_factor === state.target.formFactor) : null;
                html += validator.renderItem(ffMatch, `فرم فاکتور شاسی (${config.chassis?.form_factor}) با رک شما سازگار است`, 'در انتظار بررسی سایز شاسی...', `خطای زیرساخت: شما سایز ${state.target.formFactor} را نیاز دارید، اما شاسی انتخابی ${config.chassis?.form_factor} است!`, ['chassis-select']);
            }
            chBox.innerHTML = html;
        }

        const cpuBox = document.getElementById('validator-cpu');
        if (cpuBox) {
            let html = validator.renderItem(config.cpu ? true : null, 'مدل پردازنده تعیین شده است', 'در انتظار انتخاب پردازنده...', '', ['cpu-select']);
            let validQty = config.cpu ? (config.cpuQty <= (config.chassis?.max_cpus || 2)) : null;
            html += validator.renderItem(validQty, 'تعداد پردازنده با شاسی سازگار است', 'در انتظار بررسی تعداد مجاز پردازنده...', `شاسی انتخابی نهایتاً ${config.chassis?.max_cpus || 2} پردازنده ساپورت می‌کند!`, ['cpu-qty']);
            if (state.target.cores) {
                let totalCores = config.cpu ? (config.cpu.cores * config.cpuQty) : 0;
                let meetTarget = config.cpu ? (totalCores >= state.target.cores) : null;
                html += validator.renderItem(meetTarget, `تعداد هسته پردازشی (${totalCores} Core) نیاز شما را برآورده می‌کند`, 'در انتظار بررسی هدف (Target) هسته‌ها...', `تعداد هسته پردازشی (${totalCores}) کمتر از هدف تعیین شده (${state.target.cores} Core) است!`, ['cpu-select', 'cpu-qty']);
            }
            cpuBox.innerHTML = html;
        }

        const ramBox = document.getElementById('validator-ram');
        if (ramBox) {
            let html = validator.renderItem(config.ram ? true : null, 'مدل رم تعیین شده است', 'در انتظار انتخاب مدل رم...', '', ['ram-select']);
            let maxSlots = config.chassis?.max_ram_slots || 24;
            let validRamQty = config.ram ? (config.ramQty <= maxSlots) : null;
            html += validator.renderItem(validRamQty, 'تعداد ماژول‌های رم در محدوده مجاز است', 'در انتظار بررسی ظرفیت اسلات‌های رم...', `تعداد رم بیش از ظرفیت مادربرد (${maxSlots} عدد) است!`, ['ram-qty']);
            
            let ramBalanceStatus = null, ramBalanceMsg = 'در انتظار بررسی تقارن و بازدهی رم...';
            if (config.ram) {
                if (config.ramQty % config.cpuQty !== 0) { ramBalanceStatus = 'warning'; ramBalanceMsg = `اخطار عدم تقارن: تعداد رم‌ها (${config.ramQty}) مضربی از پردازنده‌ها (${config.cpuQty}) نیست.`; } 
                else if ((config.ramQty / config.cpuQty) % 2 !== 0) { ramBalanceStatus = 'warning'; ramBalanceMsg = 'اخطار بازدهی: اختصاص تعداد فرد رم به هر پردازنده حالت Multi-Channel را می‌شکند.'; } 
                else { ramBalanceStatus = true; ramBalanceMsg = 'چیدمان ماژول‌های رم متقارن و دارای بالاترین بازدهی است'; }
            }
            html += validator.renderItem(ramBalanceStatus, ramBalanceMsg, 'در انتظار بررسی تقارن و بازدهی رم...', ramBalanceMsg, ['ram-qty']);

            let ramSpeedStatus = null, ramSpeedMsg = 'در انتظار بررسی گلوگاه فرکانس رم و پردازنده...';
            if (config.cpu && config.ram) {
                if (config.ram.speed_mt < config.cpu.supported_ram_speed_mt) { ramSpeedStatus = 'warning'; ramSpeedMsg = `اخطار گلوگاه: فرکانس رم (${config.ram.speed_mt} MT/s) از حداکثر توان پردازنده (${config.cpu.supported_ram_speed_mt} MT/s) کمتر است.`; } 
                else { ramSpeedStatus = true; ramSpeedMsg = 'فرکانس رم با پردازنده سازگاری کامل دارد'; }
            }
            html += validator.renderItem(ramSpeedStatus, ramSpeedMsg, 'در انتظار بررسی گلوگاه فرکانس رم...', ramSpeedMsg, ['ram-select']);

            let gpuRamStatus = null, gpuRamMsg = 'در انتظار بررسی گلوگاه بین رم و گرافیک...';
            let totalSysRam = config.ram ? (config.ram.capacity_gb * config.ramQty) : 0;
            if (config.ram && config.gpu) {
                let totalGpuRam = config.gpu.memory_gb * config.gpuQty;
                gpuRamStatus = totalSysRam >= (totalGpuRam * 2);
                gpuRamMsg = `رم سیستم (${totalSysRam}GB) باید حداقل دو برابر حافظه گرافیک‌ها (${totalGpuRam * 2}GB) باشد!`;
            } else if (config.ram) {
                gpuRamStatus = true;
                gpuRamMsg = 'رم سیستم پردازش‌های بدون کارت گرافیک را مدیریت می‌کند (بلامانع)';
            }
            html += validator.renderItem(gpuRamStatus, gpuRamMsg, 'در انتظار بررسی گلوگاه بین رم و گرافیک...', gpuRamMsg, ['ram-qty']);

            if (state.target.ram) {
                let meetTarget = config.ram ? (totalSysRam >= state.target.ram) : null;
                html += validator.renderItem(meetTarget, `ظرفیت رم (${totalSysRam}GB) نیاز شما را برآورده می‌کند`, 'در انتظار بررسی هدف (Target) ظرفیت رم...', `حجم رم (${totalSysRam}GB) کمتر از هدف تعیین شده (${state.target.ram}GB) است!`, ['ram-select', 'ram-qty']);
            }
            ramBox.innerHTML = html;
        }

        const stBox = document.getElementById('validator-storage');
        const optBox = document.getElementById('validator-optical-drive');
        let maxBays = config.chassis?.storage_rules?.max_bays || 24;
        let maxBoxesAllowed = Math.floor(maxBays / 8);
        let optical_boxes = config.opticalDrives.length;
        let totalUsedBays = config.drives.reduce((sum, d) => sum + (parseInt(d.qty) || 0), 0);
        let drive_boxes = totalUsedBays > 0 ? Math.ceil(totalUsedBays / 8) : 0;
        let total_physical_boxes = optical_boxes + drive_boxes;

        if (stBox) {
            let html = validator.renderItem(config.drives.length > 0 ? config.drives.every(d => d.driveId !== "") : null, 'مدل دیسک‌های ذخیره‌سازی معتبر است', 'در انتظار افزودن هارد دیسک...', 'لطفاً مدل تمام هاردهای افزوده شده را مشخص کنید', []);
            let baysValid = (totalUsedBays > 0 || optical_boxes > 0) ? (total_physical_boxes <= maxBoxesAllowed) : null;
            html += validator.renderItem(baysValid, `فضای فیزیکی مصرفی (${total_physical_boxes} باکس) در محدوده شاسی (${maxBoxesAllowed} باکس) است`, 'در انتظار بررسی ظرفیت Drive Cage ها...', `مجموع هاردها و درایو نوری از ظرفیت فیزیکی شاسی (${maxBoxesAllowed} باکس) بیشتر شده است!`, []);

            let backplaneStatus = null, backplaneMsg = 'در انتظار بررسی وضعیت بک‌پلین‌های مورد نیاز...';
            if (total_physical_boxes > 0 && total_physical_boxes <= maxBoxesAllowed) {
                let added_backplanes = drive_boxes > 1 ? (drive_boxes - 1) : 0;
                let expanderText = config.sasExpander ? " + یک عدد کارت SAS Expander" : "";
                if (added_backplanes === 0 && !config.sasExpander) { backplaneStatus = true; backplaneMsg = 'هاردهای انتخابی روی بک‌پلین و کنترلر پیش‌فرض نصب می‌شوند (بدون هزینه اضافی)'; } 
                else { backplaneStatus = 'warning'; backplaneMsg = `نیاز به خرید قطعات رابط: ${added_backplanes > 0 ? added_backplanes + ' عدد بک‌پلین اضافی' : ''}${expanderText}`; }
            }
            html += validator.renderItem(backplaneStatus, backplaneMsg, 'در انتظار بررسی قطعات رابط هاردها...', backplaneMsg, []);

            if (state.target.storage) {
                let totalUsableStorageGb = 0;
                config.drives.forEach(d => {
                    const driveObj = state.db.drives.find(x => x.id == d.driveId);
                    let q = parseInt(d.qty) || 0;
                    const r = d.raid;
                    if (driveObj) {
                        let cap = driveObj.capacity_gb, usable = 0;
                        if (r === '1' && q === 2) usable = cap;
                        else if (r === '5' && q >= 3) usable = (q - 1) * cap;
                        else if (r === '6' && q >= 4) usable = (q - 2) * cap;
                        else if (r === '10' && q >= 4) usable = (q / 2) * cap;
                        else if (r === '50' && q >= 6) usable = (q - 2) * cap;
                        else if (r === '60' && q >= 8) usable = (q - 4) * cap;
                        else usable = q * cap;
                        totalUsableStorageGb += usable;
                    }
                });
                let meetTarget = (config.drives.length > 0) ? (totalUsableStorageGb >= state.target.storage) : null;
                html += validator.renderItem(meetTarget, `فضای خروجی رید (${(totalUsableStorageGb / 1000).toFixed(1)}TB) هدف شما را تامین می‌کند`, 'در انتظار بررسی هدف (Target) هارد...', `فضای خروجی رید (${(totalUsableStorageGb / 1000).toFixed(1)}TB) کمتر از هدف (${(state.target.storage / 1000).toFixed(1)}TB) است!`, []);
            }
            stBox.innerHTML = html;
        }

        if (optBox) {
            optBox.innerHTML = validator.renderItem(config.opticalDrives.length > 0 ? config.opticalDrives.every(o => o.opticalId !== "") : null, 'مدل درایو(های) نوری معتبر است', 'بدون درایو نوری (انتخابی)', 'لطفاً مدل تمام درایوهای نوری را مشخص کنید', []);
        }

        const raidBox = document.getElementById('validator-raid-controller');
        if (raidBox) {
            let html = '';
            let requiresHwRaid = config.drives.some(d => ['5', '6', '50', '60'].includes(d.raid));
            let activeControllerName = config.controller ? config.controller.model_name : (config.chassis?.default_controller || "S100i");
            let isHwController = activeControllerName.match(/(P|E)\d{3}i/i) !== null;
            let hwRaidStatus = requiresHwRaid ? isHwController : true;
            if (config.sasExpander) hwRaidStatus = true;

            let hwRaidMsg = '';
            if (requiresHwRaid && !isHwController && !config.sasExpander) hwRaidMsg = 'برای RAID پیشرفته حتماً باید کنترلر سخت‌افزاری انتخاب کنید!';
            else if (requiresHwRaid && !isHwController && config.sasExpander) hwRaidMsg = 'کنترل RAID پیشرفته به واسطه انتخاب SAS Expander مجاز شد';
            else hwRaidMsg = 'RAID انتخابی شما با کنترلر فعال سازگار است';

            html += validator.renderItem(hwRaidStatus, hwRaidMsg, 'در انتظار بررسی نیاز به کنترلر سخت‌افزاری...', hwRaidMsg, ['controller-select']);

            let controllerLimit = 8;
            const match = activeControllerName.match(/[A-Z]\d{1,2}(\d{2})/i);
            if (match && match[1]) controllerLimit = parseInt(match[1]);
            else if (activeControllerName.includes('S100i')) controllerLimit = 14;

            let limitText = `${controllerLimit} درایو`;
            if (config.sasExpander) { controllerLimit = 999; limitText = 'نامحدود (توسط SAS Expander)'; }

            let capValid = totalUsedBays > 0 ? (totalUsedBays <= controllerLimit) : null;
            html += validator.renderItem(capValid, `ظرفیت پورت‌ها (${limitText}) برای هاردهای شما کافی است`, 'در انتظار بررسی ظرفیت پورت‌های کنترلر...', `کنترلر فعال (${activeControllerName}) نهایتاً ${controllerLimit} هارد پشتیبانی می‌کند (SAS Expander را اضافه کنید)!`, ['controller-select', 'sas-expander-checkbox']);
            raidBox.innerHTML = html;
        }

        const hbaBox = document.getElementById('validator-hba');
        if (hbaBox) {
            hbaBox.innerHTML = validator.renderItem(config.hbas.length > 0 ? config.hbas.every(h => h.hbaId !== "") : null, 'کارت‌های HBA معتبر هستند', 'بدون کارت HBA (انتخابی)', 'لطفاً مدل HBA را مشخص کنید', []);
        }

        const gpuBox = document.getElementById('validator-gpu');
        if (gpuBox) {
            let html = validator.renderItem(config.gpu ? true : null, `کارت گرافیک ${config.gpu?.model_name || ''} انتخاب شد`, 'بدون کارت گرافیک (انتخابی)', '', []);
            if (state.target.gpu) html += validator.renderItem(config.gpu ? true : false, 'نیاز به پردازش گرافیکی (GPU) تامین شد', 'در انتظار انتخاب کارت گرافیک (اجباری طبق هدف)...', 'بر اساس کاربری شما، انتخاب حداقل یک کارت گرافیک الزامی است!', ['gpu-select']);
            gpuBox.innerHTML = html;
        }

        const netBox = document.getElementById('validator-network');
        if (netBox) {
            let html = validator.renderItem(true, `کارت شبکه پیش‌فرض مادربرد فعال است`, '', '', []);
            let allValid = config.networks.length > 0 ? config.networks.every(n => n.networkId !== "") : null;
            html += validator.renderItem(allValid, 'کارت شبکه‌های اضافی معتبر هستند', 'بدون کارت شبکه اضافه (انتخابی)', 'لطفاً مدل کارت شبکه اضافی را انتخاب کنید', []);

            let flr_cards_count = 0;
            config.networks.forEach(n => {
                let netObj = state.db.networks.find(x => x.id == n.networkId);
                if (netObj && netObj.form_factor === 'FlexibleLOM') flr_cards_count += (parseInt(n.qty) || 1);
            });
            let flrValid = flr_cards_count > 0 ? (flr_cards_count <= 1) : null;
            html += validator.renderItem(flrValid, `کارت شبکه FLR در جایگاه اختصاصی نصب شد`, 'بدون کارت شبکه FLR', `خطا: شاسی فقط یک جایگاه FLR دارد (تعداد انتخابی: ${flr_cards_count})`, []);
            netBox.innerHTML = html;
        }

        const pcieBox = document.getElementById('validator-risers');
        if (pcieBox) {
            let html = '';
            let validRiser3 = config.riser3 ? (config.cpuQty >= 2) : null;
            html += validator.renderItem(validRiser3, 'رایزر سوم فعال است', 'رایزر سوم غیرفعال است', 'رایزر سوم نیازمند پردازنده دوم است!', ['riser3-select', 'cpu-qty']);

            const pcie = calculatePcieUsage(config);
            let gpuValid = pcie.req_gpu > 0 ? (pcie.req_gpu <= pcie.avail_x16_gpu) : null;
            html += validator.renderItem(gpuValid, `تعداد گرافیک‌ها با رایزرهای 2 و 3 سازگار است`, 'بدون کارت گرافیک پردازشی', `خطا: گرافیک نیاز به رایزر 2 یا 3 دارد (نیاز: ${pcie.req_gpu} عدد | موجود در رایزرها: ${pcie.avail_x16_gpu})`, ['riser3-select', 'riser2-select']);

            let generalValid = pcie.req_general > 0 ? (pcie.req_general <= pcie.total_avail_general) : null;
            html += validator.renderItem(generalValid, `اسلات‌های PCIe برای کارت‌های جانبی کافی است`, 'بدون کارت جانبی (شبکه/HBA/کنترلر)', `خطا: کمبود اسلات برای کارت‌های جانبی (نیاز: ${pcie.req_general} | موجود: ${pcie.total_avail_general})`, (gpuValid === false) ? [] : ['riser2-select']);

            let used_x16 = Math.min(pcie.req_gpu, pcie.avail_x16_gpu);
            let used_x8 = Math.min(pcie.req_general, pcie.avail_general);
            let overflow_from_x16 = Math.max(0, pcie.req_general - pcie.avail_general);

            html += `
                <div class="mt-2 mb-2 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs space-y-2">
                    <div class="flex justify-between ${pcie.req_gpu > pcie.avail_x16_gpu ? 'text-red-600 font-bold' : 'text-gray-700'}">
                        <span>🔲 اسلات‌های x16 (رایزر ۲/۳):</span>
                        <span>${pcie.avail_x16_gpu} موجود — ${used_x16} استفاده شده (گرافیک)</span>
                    </div>
                    <div class="flex justify-between ${pcie.req_general > pcie.total_avail_general ? 'text-red-600 font-bold' : 'text-gray-700'}">
                        <span>🔳 اسلات‌های x8 (عمومی):</span>
                        <span>${pcie.avail_general} موجود — ${used_x8} استفاده شده (کارت جانبی)</span>
                    </div>
                    ${overflow_from_x16 > 0 ? `<div class="text-amber-600 font-medium pt-1 border-t border-gray-200 mt-1">↳ ${overflow_from_x16} عدد از اسلات x16 خالی رایزر برای کارت‌های عمومی قرض گرفته شد.</div>` : ''}
                </div>`;
            pcieBox.innerHTML = html;
        }

        const psuBox = document.getElementById('validator-Power');
        if (psuBox) {
            let html = validator.renderItem(config.psu ? true : null, `منبع تغذیه انتخاب شد (۲ عدد Redundant)`, 'در انتظار انتخاب پاور...', '', ['psu-select']);
            let currentWatts = parseInt(document.getElementById('summary-power').innerText) || 0;
            let safeWatts = currentWatts * 1.20;
            let totalAvailableWatts = config.psu ? (config.psu.wattage * 2) : 0;
            html += validator.renderItem(config.psu ? (totalAvailableWatts >= safeWatts) : null, `توان مجموع پاورها (${totalAvailableWatts}W) برای مصرف کل سیستم مناسب است`, 'در انتظار پاور برای محاسبه فشار بار...', `خطا: توان مجموع پاورها کمتر از حد ایمن (${Math.ceil(safeWatts)}W) است! مدل بالاتری انتخاب کنید.`, ['psu-select']);
            psuBox.innerHTML = html;
        }
    }
};

const proWizard = {
    currentStep: 1,
    totalSteps: 6,
    stepsConfig: [
        { id: 1, title: 'انتخاب شاسی', desc: 'پایه و اساس سرور خود را بر اساس معماری مدنظر مشخص کنید.', label: 'شاسی' },
        { id: 2, title: 'انتخاب رم و پردازنده', desc: 'قلب تپنده و حافظه موقت سرور را بر اساس توان پردازشی تنظیم کنید.', label: 'پردازش و رم' },
        { id: 3, title: 'انتخاب هارد و کنترلر Raid', desc: 'فضای ذخیره‌سازی و مکانیزم یکپارچه‌سازی و امنیت دیسک‌ها را تعیین کنید.', label: 'حافظه و کنترلر Raid' },
        { id: 4, title: 'انتخاب کارت های HBA و گرافیک', desc: 'گرافیک پردازشی و کارت‌های اتصال به استوریج خارجی را مشخص کنید.', label: 'کارت های HBA' },
        { id: 5, title: 'انتخاب کارت شبکه و رایزرهای توسعه', desc: 'مسیرهای ارتباطی و اسلات‌های اضافی روی مادربرد را تنظیم کنید.', label: 'کارت شبکه و رایزر توسعه' },
        { id: 6, title: 'اتصالات و تغذیه', desc: 'تامین انرژی قطعات و امکانات جانبی خواندن اطلاعات را نهایی کنید.', label: 'تغذیه و جانبی' }
    ],

    init: () => {
        proWizard.currentStep = 1;
        proWizard.render();
    },

    getStepStatus: (step) => {
        const checkDOMForErrors = (ids) => {
            let hasError = false;
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.innerHTML.includes('❌')) hasError = true;
            });
            return hasError ? 'error' : 'valid';
        };

        const config = state.currentConfig;
        switch(step) {
            case 1: if (!config.chassis) return 'pending'; return checkDOMForErrors(['validator-chassis']);
            case 2: if (!config.cpu || !config.ram) return 'pending'; return checkDOMForErrors(['validator-cpu', 'validator-ram']);
            case 3: return checkDOMForErrors(['validator-storage', 'validator-raid-controller']);
            case 4: return checkDOMForErrors(['validator-hba', 'validator-gpu']);
            case 5: return checkDOMForErrors(['validator-network', 'validator-risers']);
            case 6: if (!config.psu) return 'pending'; return checkDOMForErrors(['validator-optical-drive', 'validator-Power']);
        }
        return 'pending';
    },

    render: () => {
        for (let i = 1; i <= proWizard.totalSteps; i++) {
            const stepEl = document.getElementById(`pro-step-${i}`);
            if (stepEl) {
                if (i === proWizard.currentStep) stepEl.classList.remove('hidden');
                else stepEl.classList.add('hidden');
            }
        }

        const stepData = proWizard.stepsConfig[proWizard.currentStep - 1];
        const titleEl = document.getElementById('pro-step-title');
        const descEl = document.getElementById('pro-step-desc');
        if (titleEl) titleEl.innerText = stepData.title;
        if (descEl) descEl.innerText = stepData.desc;

        let allStepsValid = true;
        let progressHTML = `<div class="absolute top-1/2 left-0 right-0 h-0.5 bg-gray-200 -z-10"></div><div class="flex w-full justify-between">`;
        
        proWizard.stepsConfig.forEach((cfg, index) => {
            const stepNum = index + 1;
            const status = proWizard.getStepStatus(stepNum);
            if (status !== 'valid') allStepsValid = false;

            let stateClass, textClass, icon;
            if (status === 'error') {
                stateClass = stepNum === proWizard.currentStep ? 'bg-red-100 text-red-700 border-2 border-red-500 shadow-md' : 'bg-red-50 text-red-500 border border-red-300';
                textClass = 'text-red-600 font-bold'; icon = '✖';
            } else if (status === 'valid') {
                stateClass = stepNum === proWizard.currentStep ? 'bg-green-100 text-green-700 border-2 border-green-500 shadow-md' : 'bg-green-50 text-green-600 border border-green-300';
                textClass = 'text-green-700 font-bold'; icon = '✔';
            } else {
                stateClass = stepNum === proWizard.currentStep ? 'bg-blue-100 text-blue-900 border-2 border-blue-500 shadow-md' : 'bg-white text-gray-400 border border-gray-200';
                textClass = stepNum === proWizard.currentStep ? 'text-blue-900 font-bold' : 'text-gray-400 font-normal'; icon = stepNum;
            }

            progressHTML += `
                <div class="flex items-center gap-2 bg-white px-2 cursor-pointer transition hover:opacity-80" onclick="proWizard.currentStep = ${stepNum}; proWizard.render();">
                    <span class="${stateClass} flex items-center justify-center w-6 h-6 rounded-md text-xs transition-all duration-300">${icon}</span>
                    <span class="${textClass} text-xs hidden md:inline-block">${cfg.label}</span>
                </div>`;
        });
        progressHTML += `</div>`;
        
        const pBar = document.getElementById('pro-progress-bar');
        if (pBar) pBar.innerHTML = progressHTML;

        const prevBtn = document.getElementById('pro-prev-btn');
        const nextBtn = document.getElementById('pro-next-btn');
        const finalBtn = document.getElementById('pro-submit-final-btn');

        if (prevBtn) prevBtn.classList.toggle('hidden', proWizard.currentStep === 1);
        if (nextBtn) nextBtn.classList.toggle('hidden', proWizard.currentStep === proWizard.totalSteps);
        
        if (finalBtn) {
            if (allStepsValid) {
                finalBtn.classList.remove('hidden');
                document.getElementById('pro-save-btn')?.classList.add('hidden');
            } else {
                finalBtn.classList.add('hidden');
                document.getElementById('pro-save-btn')?.classList.remove('hidden');
            }
        }
    },

    next: () => { if (proWizard.currentStep < proWizard.totalSteps) { proWizard.currentStep++; proWizard.render(); } },
    prev: () => { if (proWizard.currentStep > 1) { proWizard.currentStep--; proWizard.render(); } },

    submitConfig: () => {
        const tbody = document.getElementById('pro-details-table-body');
        const codeEl = document.getElementById('pro-generated-svr-code');
        if (codeEl) codeEl.innerText = "آماده ثبت..."; 
        
        let html = '';
        const addRow = (title, desc) => {
            html += `<div class="flex justify-between items-center py-4 hover:bg-white transition px-2 rounded border-b last:border-0">
                        <button onclick="wizard.showView('view-pro-configurator')" class="text-blue-600 flex items-center gap-1 text-sm font-bold"><span class="text-lg">✎</span> ویرایش</button>
                        <div class="text-right flex-1 pr-6 font-bold text-gray-800" dir="ltr">${desc}</div>
                        <div class="text-gray-500 text-sm w-1/4 text-right">${title}</div>
                     </div>`;
        };

        if (state.currentConfig.chassis) addRow('شاسی (Chassis)', state.currentConfig.chassis.model);
        if (state.currentConfig.cpu) addRow('پردازنده (CPU)', `${state.currentConfig.cpuQty}X ${state.currentConfig.cpu.model_name}`);
        if (state.currentConfig.ram) addRow('حافظه رم (RAM)', `${state.currentConfig.ramQty}X ${state.currentConfig.ram.model_name}`);
        
        state.currentConfig.drives.forEach(d => {
            if (!d.driveId) return;
            const driveObj = state.db.drives.find(x => x.id == d.driveId);
            if (driveObj) addRow('فضای ذخیره‌سازی (Storage)', `${d.qty}X ${driveObj.model_name} (RAID: ${d.raid})`);
        });

        if (state.currentConfig.controller) addRow('کنترلر رید (RAID)', state.currentConfig.controller.model_name);
        else addRow('کنترلر رید (RAID)', state.currentConfig.chassis?.default_controller || 'پیش‌فرض مادربرد');
        if (state.currentConfig.psu) addRow('منبع تغذیه (Power Supply)', `${state.currentConfig.psuQty}X ${state.currentConfig.psu.model_name}`);

        if (tbody) tbody.innerHTML = html;
        wizard.showView('view-pro-results');
    }
};

const wizard = {
    answers: {},
    currentStepIndex: 0,

    showView: (viewId) => {
        const views = [
            'view-intro', 'view-pro', 'view-guidance', 'view-offers', 
            'view-server-details', 'view-pro-configurator', 'view-pro-results'
        ];
        views.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
        const target = document.getElementById(viewId);
        if (target) target.classList.remove('hidden');
    },

    skipAndStart: () => {
        state.target = { cores: 0, ram: 0, storage: 0, gpu: false, network: 'any', formFactor: 'any' };
        wizard.launchConfigurator();
    },

    submit: (type) => {
        if (type === 'pro') {
            state.target.cores = parseInt(document.getElementById('req-cores')?.value) || 16;
            state.target.ram = parseInt(document.getElementById('req-ram')?.value) || 64;
            state.target.storage = parseFloat(document.getElementById('req-storage-tb')?.value) * 1000 || 2000;
            state.target.gpu = document.getElementById('req-gpu-pro')?.value === 'yes';
            state.target.network = 'any';
            state.target.formFactor = 'any';
        }
        wizard.launchConfigurator();
    },

    launchConfigurator: () => {
        state.activeMode = 'pro';
        wizard.showView('view-pro-configurator');
        proWizard.init();
        api.fetchData();
    },

    initGuidance: () => {
        state.activeMode = 'guidance';
        wizard.answers = {};
        wizard.currentStepIndex = 0;
        wizard.showView('view-guidance');
        wizard.renderStep();
    },

    renderStep: () => {
        const step = guidanceConfig[wizard.currentStepIndex];
        document.getElementById('guidance-title').innerText = step.title;
        document.getElementById('guidance-subtitle').innerText = step.sub;

        let progressHTML = `<div class="absolute top-1/2 left-0 right-0 h-0.5 bg-gray-200 -z-10"></div>`;
        for (let i = 0; i < guidanceConfig.length; i++) {
            let stateClass = i < wizard.currentStepIndex ? 'bg-green-100 text-green-700 border border-green-300' : 
                             (i === wizard.currentStepIndex ? 'bg-blue-100 text-blue-900' : 'bg-white text-gray-400 border border-gray-200');
            let content = i < wizard.currentStepIndex ? '✔' : `0${i + 1}`;
            progressHTML += `<span class="${stateClass} px-3 py-1 rounded-md z-10 font-mono text-xs shadow-sm">${content}</span>`;
        }
        document.getElementById('guidance-progress').innerHTML = progressHTML;

        let html = step.tip ? `<p class="text-yellow-600 text-xs font-bold flex items-center gap-1 mb-6"><span>💡</span> ${step.tip}</p>` : '';
        html += `<div class="${step.type === 'checkbox' ? 'grid md:grid-cols-3 gap-y-4 gap-x-2' : 'space-y-3'}">`;
        
        step.options.forEach(opt => {
            let isChecked = wizard.answers[step.id] && wizard.answers[step.id].includes(opt.val) ? 'checked' : '';
            if (step.type === 'radio') {
                html += `
                <label class="flex justify-between items-center p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition ${isChecked ? 'ring-2 ring-blue-500 bg-blue-50' : ''}">
                    <span class="text-xs text-gray-500">${opt.desc}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-gray-800">${opt.label}</span>
                        <input type="radio" name="step_${step.id}" value="${opt.val}" class="w-5 h-5 text-blue-600 focus:ring-blue-500" onchange="wizard.validateStep()" ${isChecked}>
                    </div>
                </label>`;
            } else {
                html += `
                <label class="flex items-center justify-end gap-3 cursor-pointer flex-row-reverse p-2 hover:bg-gray-50 rounded transition">
                    <span class="text-sm text-gray-700 font-medium">${opt.label}</span>
                    <input type="checkbox" value="${opt.val}" class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="wizard.validateStep()" ${isChecked}>
                </label>`;
            }
        });
        html += `</div>`;
        document.getElementById('guidance-content').innerHTML = html;

        document.getElementById('guidance-prev-btn').classList.toggle('hidden', wizard.currentStepIndex === 0);
        const nextBtn = document.getElementById('guidance-next-btn');
        nextBtn.innerText = wizard.currentStepIndex === guidanceConfig.length - 1 ? 'مشاهده پیشنهادات ←' : 'سوال بعد ←';
        wizard.validateStep();
    },

    validateStep: () => {
        const step = guidanceConfig[wizard.currentStepIndex];
        const inputs = document.querySelectorAll(`#guidance-content input:checked`);
        const nextBtn = document.getElementById('guidance-next-btn');
        
        if (inputs.length > 0) {
            wizard.answers[step.id] = Array.from(inputs).map(el => el.value);
            nextBtn.disabled = false;
            nextBtn.className = 'px-8 py-3 bg-blue-900 text-white font-bold rounded-lg hover:bg-blue-800 transition shadow-md';
        } else {
            nextBtn.disabled = true;
            nextBtn.className = 'px-8 py-3 bg-gray-200 text-gray-500 font-bold rounded-lg cursor-not-allowed transition';
        }
        sessionManager.save('draft');
    },

    nextStep: () => {
        if (wizard.currentStepIndex < guidanceConfig.length - 1) {
            wizard.currentStepIndex++;
            wizard.renderStep();
        } else {
            wizard.showView('view-offers');
        }
    },

    prevStep: () => {
        if (wizard.currentStepIndex > 0) {
            wizard.currentStepIndex--;
            wizard.renderStep();
        } else {
            wizard.showView('view-intro');
        }
    },

    selectOffer: (tier) => {
        const demoData = [
            { title: 'شاسی (Chassis)', desc: 'DL360 (2U) - Base: 8SFF, Max: 24 Bays' },
            { title: 'پردازنده (CPU)', desc: '2X Intel Xeon Bronze 3104 (6C 8.25M Cache 1.70 GHz)' },
            { title: 'حافظه رم (RAM)', desc: '2X 16GB DDR4 RDIMM 2133MHz' },
            { title: 'فضای ذخیره سازی (Storage)', desc: '2 × SSD 960GB SAS 2.5" + Tray Caddy' },
            { title: 'کنترلر رید (RAID)', desc: 'RAID HPE S100i (Only sata disks)' },
            { title: 'منبع تغذیه (Power Supply)', desc: 'hpe 1600w flex slot platinum hot plug' }
        ];

        let tbody = '';
        demoData.forEach(item => {
            tbody += `
            <div class="flex justify-between items-center py-4 hover:bg-white transition px-2 rounded border-b last:border-0">
                <button onclick="wizard.launchConfigurator()" class="text-blue-600 flex items-center gap-1 text-sm font-bold"><span class="text-lg">✎</span> ویرایش</button>
                <div class="text-right flex-1 pr-6 font-bold text-gray-800" dir="ltr">${item.desc}</div>
                <div class="text-gray-500 text-sm w-1/4 text-right">${item.title}</div>
            </div>`;
        });
        document.getElementById('details-table-body').innerHTML = tbody;
        wizard.showView('view-server-details');
    },

    submitFinalGuidance: () => {
        document.getElementById('modal-final-success').classList.remove('hidden');
    }
};

const configurator = {
    initDropdowns: () => {
        const chassisSelect = document.getElementById('chassis-select');
        if (chassisSelect && state.db.chassis) {
            chassisSelect.innerHTML = '<option value="">شاسی مورد نظر را انتخاب کنید...</option>';
            state.db.chassis.forEach(c => {
                chassisSelect.innerHTML += `<option value="${c.id}">${c.model} (${c.form_factor}) - Base: ${c.storage_rules?.base_bays || '?'}${c.storage_rules?.base_drive_type || 'SFF'}</option>`;
            });
        }
    },

    populateChassisSpecificDropdowns: () => {
        const populate = (id, data) => {
            const el = document.getElementById(id);
            if (el && data) {
                el.innerHTML = el.querySelector('option[value=""]')?.outerHTML || '<option value="">انتخاب کنید...</option>';
                data.forEach(item => { el.innerHTML += `<option value="${item.id}">${item.model_name}</option>`; });
            }
        };

        const ctrlSelect = document.getElementById('controller-select');
        if (ctrlSelect && state.db.controllers) {
            ctrlSelect.innerHTML = '<option value="">استفاده از کنترلر پیش‌فرض مادربرد</option>';
            state.db.controllers.forEach(c => {
                if (c.form_factor === 'Standup PCIe') {
                    ctrlSelect.innerHTML += `<option value="${c.id}">${c.model_name}</option>`;
                }
            });
        }
        populate('gpu-select', state.db.gpus);
        populate('psu-select', state.db.psus);
        populate('riser2-select', state.db.risers);
        populate('riser3-select', state.db.risers);
        populate('optical-select', state.db.optical_drives);
    },

    handleChassisChange: async () => {
        const id = document.getElementById('chassis-select').value;
        if (!id) return;
        state.currentConfig.chassis = state.db.chassis.find(c => c.id == id);

        const ok = await api.fetchChassisData(id);
        if (!ok) return;
        configurator.populateChassisSpecificDropdowns();

        ['cpu-select', 'cpu-qty', 'add-drive-btn', 'add-optical-btn', 'controller-select', 'sas-expander-checkbox', 'add-hba-btn', 'gpu-select', 'add-network-btn', 'psu-select', 'riser2-select', 'riser3-select'].forEach(el => {
            const element = document.getElementById(el);
            if (element) element.disabled = false;
        });

        const defNetEl = document.getElementById('default-network-display');
        if (defNetEl && state.currentConfig.chassis.default_network) {
            defNetEl.innerHTML = `✅ <b>کارت شبکه پیش‌فرض:</b> ${state.currentConfig.chassis.default_network}`;
            defNetEl.classList.remove('hidden');
        }

        const defCtrlEl = document.getElementById('default-controller-display');
        if (defCtrlEl && state.currentConfig.chassis.default_controller) {
            defCtrlEl.innerHTML = `✅ <b>کنترلر پیش‌فرض:</b> ${state.currentConfig.chassis.default_controller}`;
            defCtrlEl.classList.remove('hidden');
        }

        const cpuSelect = document.getElementById('cpu-select');
        if (cpuSelect) {
            cpuSelect.innerHTML = '<option value="">پردازنده را انتخاب کنید...</option>';
            state.db.cpus.forEach(c => {
                if (c.cores * state.currentConfig.chassis.max_cpus >= (state.target.cores || 0)) {
                    cpuSelect.innerHTML += `<option value="${c.id}">${c.model_name} (${c.cores} Cores)</option>`;
                }
            });
        }

        state.currentConfig.drives = [];
        state.currentConfig.networks = [];
        state.currentConfig.hbas = [];
        state.currentConfig.opticalDrives = [];

        configurator.renderDrives();
        configurator.renderNetworks();
        configurator.renderHbas();
        configurator.renderOpticals();
        configurator.calculateSummary();
    },

    handleCpuChange: () => {
        const id = document.getElementById('cpu-select').value;
        if (!id) return;
        state.currentConfig.cpu = state.db.cpus.find(c => c.id == id);
        state.currentConfig.cpuQty = parseInt(document.getElementById('cpu-qty').value);

        const riser3Select = document.getElementById('riser3-select');
        const riser3Warning = document.getElementById('riser3-warning');
        if (riser3Select) {
            if (state.currentConfig.cpuQty < 2) {
                riser3Select.value = ""; riser3Select.disabled = true;
                if (riser3Warning) riser3Warning.classList.remove('hidden');
                state.currentConfig.riser3 = null;
            } else {
                riser3Select.disabled = false;
                if (riser3Warning) riser3Warning.classList.add('hidden');
            }
        }

        const ramSelect = document.getElementById('ram-select');
        const ramQtyInput = document.getElementById('ram-qty');
        if (ramSelect) ramSelect.disabled = false;
        if (ramQtyInput) {
            ramQtyInput.disabled = false;
            ramQtyInput.max = Math.min(state.currentConfig.chassis.max_ram_slots || 24, state.currentConfig.cpuQty * (state.currentConfig.chassis.ram_slots_per_cpu || 12));
            if (parseInt(ramQtyInput.value) > ramQtyInput.max) ramQtyInput.value = ramQtyInput.max;
        }

        if (ramSelect) {
            ramSelect.innerHTML = '<option value="">رم را انتخاب کنید...</option>';
            state.db.rams.forEach(r => {
                const cpuOk = !r.compatible_cpu_ids || r.compatible_cpu_ids.length === 0 || r.compatible_cpu_ids.includes(state.currentConfig.cpu.id);
                if (cpuOk && (r.capacity_gb * ramQtyInput.max) >= (state.target.ram || 0)) {
                    ramSelect.innerHTML += `<option value="${r.id}">${r.model_name}</option>`;
                }
            });
        }
        configurator.handleRamChange();
        configurator.handlePCIeChange();
    },

    handleRamChange: () => {
        const id = document.getElementById('ram-select').value;
        if (!id) return;
        state.currentConfig.ram = state.db.rams.find(r => r.id == id);
        let qtyInput = document.getElementById('ram-qty');
        if (qtyInput) {
            let qty = parseInt(qtyInput.value) || 1;
            if (qty > qtyInput.max) { qty = qtyInput.max; qtyInput.value = qty; }
            state.currentConfig.ramQty = qty;
        }
        configurator.calculateSummary();
    },

    addOpticalRow: () => {
        if (state.currentConfig.opticalDrives.length >= 2) { alert("حداکثر ۲ درایو نوری مجاز است!"); return; }
        state.currentConfig.opticalDrives.push({ opticalId: "", qty: 1 });
        configurator.renderOpticals();
        configurator.calculateSummary();
    },

    removeOpticalRow: (index) => {
        state.currentConfig.opticalDrives.splice(index, 1);
        configurator.renderOpticals();
        configurator.calculateSummary();
    },

    updateOpticalRow: (index, field, value) => {
        state.currentConfig.opticalDrives[index][field] = value;
        configurator.calculateSummary();
    },

    renderOpticals: () => {
        const container = document.getElementById('opticals-container');
        if (!container) return;
        container.innerHTML = '';
        const selectedIds = state.currentConfig.opticalDrives.map(o => o.opticalId).filter(id => id !== "");

        state.currentConfig.opticalDrives.forEach((o, index) => {
            let optionsHTML = '<option value="">انتخاب درایو نوری...</option>';
            state.db.optical_drives.forEach(opt => {
                const isSelectedElsewhere = selectedIds.includes(opt.id.toString()) && o.opticalId != opt.id;
                if (!isSelectedElsewhere) {
                    const selected = (o.opticalId == opt.id) ? 'selected' : '';
                    optionsHTML += `<option value="${opt.id}" ${selected}>${opt.model_name}</option>`;
                }
            });
            container.innerHTML += `
                <div class="flex gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded-lg">
                    <select class="w-3/4 border border-gray-300 p-2 rounded bg-white text-xs" onchange="configurator.updateOpticalRow(${index}, 'opticalId', this.value)">${optionsHTML}</select>
                    <input type="number" class="w-1/4 border border-gray-300 p-2 rounded bg-gray-100 text-sm text-center" value="1" disabled title="هر ردیف یک درایو">
                    <button class="w-10 h-10 bg-red-50 text-red-600 rounded border border-red-100 hover:bg-red-100 font-bold transition" onclick="configurator.removeOpticalRow(${index})">X</button>
                </div>
            `;
        });
        const addBtn = document.getElementById('add-optical-btn');
        if (addBtn) addBtn.disabled = state.currentConfig.opticalDrives.length >= 2;
    },

    addDriveRow: () => {
        state.currentConfig.drives.push({ driveId: "", qty: 1, raid: "none" });
        configurator.renderDrives();
        configurator.calculateSummary();
    },

    removeDriveRow: (index) => {
        state.currentConfig.drives.splice(index, 1);
        configurator.renderDrives();
        configurator.calculateSummary();
    },

    updateDriveRow: (index, field, value) => {
        const driveState = state.currentConfig.drives[index];
        if (!driveState) return;
        driveState[field] = value;

        let q = parseInt(driveState.qty) || 1;
        const raid = driveState.raid;

        if (field === 'raid' || field === 'qty') {
            if (raid === '0' && q < 2) q = 2;
            if (raid === '1' && q !== 2) q = 2;
            if (raid === '5' && q < 3) q = 3;
            if (raid === '6' && q < 4) q = 4;
            if (raid === '10') { if (q < 4) q = 4; if (q % 2 !== 0) q += 1; }
            if (raid === '50' && q < 6) q = 6;
            if (raid === '60' && q < 8) q = 8;
            driveState.qty = q;
        }
        configurator.renderDrives();
        configurator.calculateSummary();
    },

    renderDrives: () => {
        const container = document.getElementById('drives-container');
        if (!container) return;
        container.innerHTML = '';

        let maxChassisBays = state.currentConfig.chassis?.storage_rules?.max_bays || 8;
        let totalUsed = 0;
        state.currentConfig.drives.forEach(d => { totalUsed += (parseInt(d.qty) || 0); });

        state.currentConfig.drives.forEach((d, index) => {
            const allowedFormFactor = state.currentConfig.chassis?.storage_rules?.base_drive_type;
            let optionsHTML = '<option value="">انتخاب هارد...</option>';

            state.db.drives.forEach(drive => {
                if (!allowedFormFactor || drive.form_factor === allowedFormFactor) {
                    if ((drive.capacity_gb * maxChassisBays) >= (state.target.storage || 0)) {
                        const selected = (d.driveId == drive.id) ? 'selected' : '';
                        optionsHTML += `<option value="${drive.id}" ${selected}>${drive.model_name}</option>`;
                    }
                }
            });

            let currentQty = parseInt(d.qty) || 1;
            let otherUsed = totalUsed - currentQty;
            let maxAttr = maxChassisBays - otherUsed;
            if (maxAttr < 1) maxAttr = 1;

            if (currentQty > maxAttr) {
                currentQty = maxAttr;
                state.currentConfig.drives[index].qty = currentQty;
            }

            let qtyOptionsHTML = '';
            for (let i = 1; i <= maxAttr; i++) {
                let isValid = true;
                if (d.raid === '0' && i < 2) isValid = false;
                if (d.raid === '1' && i !== 2) isValid = false;
                if (d.raid === '5' && i < 3) isValid = false;
                if (d.raid === '6' && i < 4) isValid = false;
                if (d.raid === '10' && (i < 4 || i % 2 !== 0)) isValid = false;
                if (d.raid === '50' && i < 6) isValid = false;
                if (d.raid === '60' && i < 8) isValid = false;

                if (isValid) {
                    let sel = (i === currentQty) ? 'selected' : '';
                    qtyOptionsHTML += `<option value="${i}" ${sel}>${i} عدد</option>`;
                }
            }

            container.innerHTML += `
                <div class="flex gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded-lg">
                    <select class="w-1/2 border border-gray-300 p-2 rounded bg-white text-xs outline-none focus:ring-1" onchange="configurator.updateDriveRow(${index}, 'driveId', this.value)">${optionsHTML}</select>
                    <select class="w-1/4 border border-gray-300 p-2 rounded bg-white text-xs outline-none focus:ring-1" onchange="configurator.updateDriveRow(${index}, 'raid', this.value)">
                        <option value="none" ${d.raid === 'none' ? 'selected' : ''}>بدون رید (JBOD)</option>
                        <option value="0" ${d.raid === '0' ? 'selected' : ''}>RAID 0</option>
                        <option value="1" ${d.raid === '1' ? 'selected' : ''}>RAID 1</option>
                        <option value="5" ${d.raid === '5' ? 'selected' : ''}>RAID 5</option>
                        <option value="6" ${d.raid === '6' ? 'selected' : ''}>RAID 6</option>
                        <option value="10" ${d.raid === '10' ? 'selected' : ''}>RAID 10</option>
                        <option value="50" ${d.raid === '50' ? 'selected' : ''}>RAID 50</option>
                        <option value="60" ${d.raid === '60' ? 'selected' : ''}>RAID 60</option>
                    </select>
                    <select class="w-1/6 border border-gray-300 p-2 rounded bg-white text-sm font-bold outline-none" onchange="configurator.updateDriveRow(${index}, 'qty', this.value)">
                        ${qtyOptionsHTML}
                    </select>
                    <button class="w-10 h-10 bg-red-50 text-red-600 rounded border border-red-100 hover:bg-red-100 font-bold transition" onclick="configurator.removeDriveRow(${index})">X</button>
                </div>
            `;
        });
    },

    addHbaRow: () => { state.currentConfig.hbas.push({ hbaId: "", qty: 1 }); configurator.renderHbas(); configurator.calculateSummary(); },
    removeHbaRow: (index) => { state.currentConfig.hbas.splice(index, 1); configurator.renderHbas(); configurator.calculateSummary(); },
    updateHbaRow: (index, field, value) => { state.currentConfig.hbas[index][field] = value; configurator.calculateSummary(); },

    renderHbas: () => {
        const container = document.getElementById('hbas-container');
        if (!container) return;
        container.innerHTML = '';
        state.currentConfig.hbas.forEach((h, index) => {
            let optionsHTML = '<option value="">انتخاب کارت HBA...</option>';
            state.db.hbas.forEach(hba => {
                const selected = (h.hbaId == hba.id) ? 'selected' : '';
                optionsHTML += `<option value="${hba.id}" ${selected}>${hba.model_name}</option>`;
            });
            let qty = parseInt(h.qty) || 1;
            container.innerHTML += `
                <div class="flex gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded-lg">
                    <select class="w-3/4 border border-gray-300 p-2 rounded bg-white text-xs outline-none" onchange="configurator.updateHbaRow(${index}, 'hbaId', this.value)">${optionsHTML}</select>
                    <input type="number" min="1" max="6" class="w-1/4 border border-gray-300 p-2 rounded bg-white text-sm text-center outline-none" value="${qty}" oninput="configurator.updateHbaRow(${index}, 'qty', this.value)">
                    <button class="w-10 h-10 bg-red-50 text-red-600 rounded border border-red-100 hover:bg-red-100 font-bold transition" onclick="configurator.removeHbaRow(${index})">X</button>
                </div>
            `;
        });
    },

    addNetworkRow: () => { state.currentConfig.networks.push({ networkId: "", qty: 1 }); configurator.renderNetworks(); configurator.calculateSummary(); },
    removeNetworkRow: (index) => { state.currentConfig.networks.splice(index, 1); configurator.renderNetworks(); configurator.calculateSummary(); },
    updateNetworkRow: (index, field, value) => { state.currentConfig.networks[index][field] = value; configurator.calculateSummary(); },

    renderNetworks: () => {
        const container = document.getElementById('networks-container');
        if (!container) return;
        container.innerHTML = '';
        state.currentConfig.networks.forEach((n, index) => {
            let optionsHTML = '<option value="">انتخاب کارت شبکه...</option>';
            let badgeHTML = '';

            state.db.networks.forEach(net => {
                const selected = (n.networkId == net.id) ? 'selected' : '';
                optionsHTML += `<option value="${net.id}" ${selected}>${net.model_name}</option>`;

                if (n.networkId == net.id) {
                    if (net.model_name.toUpperCase().includes('SFP')) {
                        badgeHTML += `<div class="mt-2"><span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-[10px] font-bold border border-purple-200">🛜 فیبر نوری (SFP)</span></div>`;
                    }
                    if (net.form_factor === 'FlexibleLOM') {
                        badgeHTML += `<div class="mt-2"><span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-[10px] font-bold border border-gray-300">پورت FLR (بدون مصرف PCIe)</span></div>`;
                    }
                }
            });

            let qty = parseInt(n.qty) || 1;
            container.innerHTML += `
                <div class="bg-gray-50 p-2 border border-gray-200 rounded-lg">
                    <div class="flex gap-2 items-center">
                        <select class="w-3/4 border border-gray-300 p-2 rounded bg-white text-xs outline-none" onchange="configurator.updateNetworkRow(${index}, 'networkId', this.value)">${optionsHTML}</select>
                        <input type="number" class="w-1/4 border border-gray-300 p-2 rounded bg-white text-sm text-center outline-none" value="${qty}" min="1" oninput="configurator.updateNetworkRow(${index}, 'qty', this.value)">
                        <button class="w-10 h-10 bg-red-50 text-red-600 rounded border border-red-100 hover:bg-red-100 font-bold transition" onclick="configurator.removeNetworkRow(${index})">X</button>
                    </div>
                    ${badgeHTML}
                </div>
            `;
        });
    },

    handlePCIeChange: () => {
        const controllerId = document.getElementById('controller-select').value;
        const gpuId = document.getElementById('gpu-select').value;
        const riser2Id = document.getElementById('riser2-select').value;
        const riser3Id = document.getElementById('riser3-select').value;
        const sasExpanderEl = document.getElementById('sas-expander-checkbox');

        state.currentConfig.controller = controllerId ? state.db.controllers.find(c => c.id == controllerId) : null;
        state.currentConfig.riser2 = riser2Id ? state.db.risers.find(r => r.id == riser2Id) : null;
        state.currentConfig.riser3 = riser3Id ? state.db.risers.find(r => r.id == riser3Id) : null;
        state.currentConfig.sasExpander = sasExpanderEl ? sasExpanderEl.checked : false;

        const gpuQtyContainer = document.getElementById('gpu-qty-container');
        if (!gpuId) {
            state.currentConfig.gpu = null;
            state.currentConfig.gpuQty = 0;
            if (gpuQtyContainer) gpuQtyContainer.classList.add('hidden');
        } else {
            state.currentConfig.gpu = state.db.gpus.find(g => g.id == gpuId);
            state.currentConfig.gpuQty = parseInt(document.getElementById('gpu-qty').value) || 1;
            if (gpuQtyContainer) { gpuQtyContainer.classList.remove('hidden'); document.getElementById('gpu-qty').disabled = false; }
        }

        configurator.renderDrives();
        configurator.calculateSummary();
    },

    handlePsuChange: () => {
        const id = document.getElementById('psu-select').value;
        const psuDisplay = document.getElementById('psu-calc-display');

        state.currentConfig.psuQty = state.currentConfig.chassis?.max_psu_bays || 2;

        if (!id) {
            state.currentConfig.psu = null;
            if (psuDisplay) psuDisplay.classList.add('hidden');
        } else {
            state.currentConfig.psu = state.db.psus.find(p => p.id == id);
            if (psuDisplay) {
                psuDisplay.classList.remove('hidden');
                psuDisplay.innerText = `۲ عدد پاور ${state.currentConfig.psu.wattage}W نصب می‌شود که توان مجموع آن ${state.currentConfig.psu.wattage * 2}W است.`;
            }
        }
        configurator.calculateSummary();
    },

    calculateSummary: () => {
        let watts = state.currentConfig.chassis?.base_power_watts ?? 100;
        let requiresHighPerfFan = false;
        let totalUsableStorageGb = 0;
        let totalRawStorageGb = 0;
        let totalDriveBaysUsed = 0;
        let requiresHwRaid = false;

        if (state.currentConfig.cpu) {
            watts += (state.currentConfig.cpu.tdp_watts * state.currentConfig.cpuQty);
            const coreEl = document.getElementById('summary-cores');
            if (coreEl) coreEl.innerText = state.currentConfig.cpu.cores * state.currentConfig.cpuQty;
            if (state.currentConfig.cpu.cooling_requirements?.requires_high_perf_fan) requiresHighPerfFan = true;
        }

        let totalSysRamGb = 0;
        if (state.currentConfig.ram) {
            watts += (state.currentConfig.ram.power_consumption_watts * state.currentConfig.ramQty);
            totalSysRamGb = state.currentConfig.ram.capacity_gb * state.currentConfig.ramQty;
            const ramEl = document.getElementById('summary-ram');
            if (ramEl) ramEl.innerText = `${totalSysRamGb} GB`;
        }

        let hasOptical = false;
        state.currentConfig.opticalDrives.forEach(o => {
            if (o.opticalId) {
                const optObj = state.db.optical_drives.find(x => x.id == o.opticalId);
                if (optObj) { watts += optObj.power_consumption_watts; hasOptical = true; }
            }
        });
        const optSummary = document.getElementById('summary-optical');
        if (optSummary) optSummary.innerText = hasOptical ? 'دارد' : 'ندارد';

        let activeRaids = new Set();
        state.currentConfig.drives.forEach(d => {
            if (d.driveId) {
                const driveObj = state.db.drives.find(x => x.id == d.driveId);
                let q = parseInt(d.qty) || 0;
                const r = d.raid;

                if (driveObj) {
                    watts += (driveObj.power_consumption_watts * q);
                    totalDriveBaysUsed += q;
                    totalRawStorageGb += (driveObj.capacity_gb * q);

                    if (r && r !== '0' && r !== 'none') activeRaids.add('RAID ' + r);

                    let usable = 0;
                    const cap = driveObj.capacity_gb;

                    if (r === '1' && q === 2) usable = cap;
                    else if (r === '5' && q >= 3) { usable = (q - 1) * cap; requiresHwRaid = true; }
                    else if (r === '6' && q >= 4) { usable = (q - 2) * cap; requiresHwRaid = true; }
                    else if (r === '10' && q >= 4) usable = (q / 2) * cap;
                    else if (r === '50' && q >= 6) { usable = (q - 2) * cap; requiresHwRaid = true; }
                    else if (r === '60' && q >= 8) { usable = (q - 4) * cap; requiresHwRaid = true; }
                    else usable = q * cap;

                    totalUsableStorageGb += usable;
                    if (driveObj.requires_high_perf_fan == 1) requiresHighPerfFan = true;
                }
            }
        });

        const storageEl = document.getElementById('summary-storage');
        if (storageEl) {
            let raidText = activeRaids.size > 0 ? ` (${Array.from(activeRaids).join(', ')})` : '';
            storageEl.innerText = `${(totalUsableStorageGb / 1000).toFixed(1)} TB${raidText}`;
        }

        const ctrlSelect = document.getElementById('controller-select');
        if (ctrlSelect) {
            let valIsNowInvalid = false;
            Array.from(ctrlSelect.options).forEach(opt => {
                if (!opt.value) return;
                const ctrlObj = state.db.controllers.find(c => c.id == opt.value);
                if (ctrlObj) {
                    let limit = 8;
                    const match = ctrlObj.model_name.match(/[A-Z]\d{1,2}(\d{2})/i);
                    if (match && match[1]) limit = parseInt(match[1]);

                    if (state.currentConfig.sasExpander) limit = 999;

                    if (totalDriveBaysUsed > limit) {
                        opt.disabled = true;
                        opt.text = `${ctrlObj.model_name} (محدود به ${match ? parseInt(match[1]) : 8} هارد - نیازمند Expander)`;
                        if (ctrlSelect.value == opt.value) valIsNowInvalid = true;
                    } else {
                        opt.disabled = false;
                        opt.text = ctrlObj.model_name;
                    }
                }
            });

            if (valIsNowInvalid) { ctrlSelect.value = ""; state.currentConfig.controller = null; }
        }

        let added_backplanes = (totalDriveBaysUsed > 8) ? Math.ceil((totalDriveBaysUsed - 8) / 8) : 0;
        const backplaneSummary = document.getElementById('summary-backplane');
        if (backplaneSummary) backplaneSummary.innerText = added_backplanes > 0 ? `بله (${added_backplanes} عدد اضافی)` : "خیر (فقط پیش‌فرض)";

        const hwRaidWarning = document.getElementById('hw-raid-warning');
        if (hwRaidWarning) (requiresHwRaid && !state.currentConfig.controller) ? hwRaidWarning.classList.remove('hidden') : hwRaidWarning.classList.add('hidden');

        let totalGpuRamGb = 0;
        if (state.currentConfig.gpu) {
            let q = state.currentConfig.gpuQty;
            watts += (state.currentConfig.gpu.tdp_watts * q);
            totalGpuRamGb = state.currentConfig.gpu.memory_gb * q;
            if (state.currentConfig.gpu.requires_high_perf_fan == 1) requiresHighPerfFan = true;
        }

        let raidSummaryText = state.currentConfig.controller ? state.currentConfig.controller.model_name : 'پیش‌فرض مادربرد';
        if (state.currentConfig.sasExpander) raidSummaryText += ' + SAS Expander';
        const sRaid = document.getElementById('summary-raid');
        if (sRaid) sRaid.innerText = raidSummaryText;

        const sGpu = document.getElementById('summary-gpu');
        if (sGpu) sGpu.innerText = state.currentConfig.gpu ? `${state.currentConfig.gpu.model_name} (${totalGpuRamGb}GB VRAM)` : 'ندارد';

        let riserCount = 0;
        if (state.currentConfig.riser2) riserCount++;
        if (state.currentConfig.riser3) riserCount++;
        const sRiser = document.getElementById('summary-risers');
        if (sRiser) sRiser.innerText = `${riserCount} عدد اضافه شده`;

        const fanEl = document.getElementById('summary-fan');
        if (fanEl) {
            if (requiresHighPerfFan) { fanEl.innerText = 'High Performance'; fanEl.className = 'font-bold text-red-600'; }
            else { fanEl.innerText = 'Standard'; fanEl.className = 'font-bold text-green-700'; }
        }

        const sPower = document.getElementById('summary-power');
        if (sPower) sPower.innerText = `${watts} W`;

        const safeWatts = watts * 1.20;
        const psuSelect = document.getElementById('psu-select');

        if (psuSelect && state.db?.psus) {
            Array.from(psuSelect.options).forEach(opt => {
                if (!opt.value) return;
                const psuObj = state.db.psus.find(p => p.id == opt.value);
                if (psuObj) {
                    if (psuObj.wattage < safeWatts) {
                        opt.disabled = true;
                        if (!opt.text.includes('(ضعیف)')) opt.text = `${psuObj.model_name} (ضعیف - توان لازم: ${Math.ceil(safeWatts)}W)`;
                    } else {
                        opt.disabled = false;
                        opt.text = psuObj.model_name;
                    }
                }
            });
        }

        const psuSummary = document.getElementById('summary-psu');
        if (psuSummary) psuSummary.innerText = state.currentConfig.psu ? `۲ عدد ${state.currentConfig.psu.model_name}` : 'انتخاب نشده';

        validator.runChecks();
        if (document.getElementById('view-pro-configurator') && !document.getElementById('view-pro-configurator').classList.contains('hidden')) {
            proWizard.render();
        }
        
        sessionManager.save('draft');
    },

    submitFinal: async () => {
        if (!state.currentConfig.chassis || !state.currentConfig.cpu || !state.currentConfig.ram || !state.currentConfig.psu) {
            alert("خطای سیستمی: قطعات اصلی (شاسی، پردازنده، رم و پاور) انتخاب نشده‌اند.");
            return;
        }

        let hasError = false;
        document.querySelectorAll('[id^="validator-"] > div').forEach(div => {
            if (div.innerText.includes('❌')) hasError = true;
        });

        if (hasError) {
            alert("اخطار معماری: کانفیگ شما دارای خطای سخت‌افزاری است. لطفاً موارد مشخص شده با علامت ❌ را برطرف کنید.");
            return;
        }

        const btn = document.activeElement; 
        const originalText = btn ? btn.innerText : 'درخواست پیش فاکتور';
        if (btn && btn.tagName === 'BUTTON') { btn.innerText = "در حال ثبت درخواست..."; btn.disabled = true; }

        state.currentConfig.drives = state.currentConfig.drives.filter(d => d.driveId !== "");
        state.currentConfig.networks = state.currentConfig.networks.filter(n => n.networkId !== "");
        state.currentConfig.hbas = state.currentConfig.hbas.filter(h => h.hbaId !== "");
        state.currentConfig.opticalDrives = state.currentConfig.opticalDrives.filter(o => o.opticalId !== "");
        state.currentConfig.totalWatts = parseInt(document.getElementById('summary-power').innerText) || 0;
        state.currentConfig.psuQty = state.currentConfig.chassis?.max_psu_bays || 2;
        
        try {
            const response = await fetch('api/submit_config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ target: state.target, config: state.currentConfig })
            });
            const result = await response.json();

            if (result.status === 'success') {
                sessionManager.save('completed', result.tracking_code);
                uiRenderer.generateInvoice(state.currentConfig, result.tracking_code);
            } else {
                alert("خطای سرور: " + result.message);
            }
        } catch (e) {
            alert("خطای ارتباط با API. بررسی لاگ شبکه الزامی است.");
        } finally {
            if (btn && btn.tagName === 'BUTTON') { btn.innerText = originalText; btn.disabled = false; }
        }
    }
};

// اجرای اولیه
document.addEventListener('DOMContentLoaded', () => {
    sessionManager.init();
    wizard.showView('view-intro');
    
    document.querySelectorAll('#view-pro input, #view-pro select').forEach(el => {
        el.addEventListener('input', () => {
            const btn = document.getElementById('pro-submit-btn');
            if (btn) {
                btn.classList.remove('bg-gray-200', 'text-gray-500', 'cursor-not-allowed');
                btn.classList.add('bg-blue-900', 'text-white', 'hover:bg-blue-800');
                btn.disabled = false;
            }
        });
    });
});