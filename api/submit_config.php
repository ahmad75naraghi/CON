<?php
// api/submit_config.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$inputJSON = file_get_contents('php://input');
$request = json_decode($inputJSON, true);

if (!$request || empty($request['config']['chassis']['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'اطلاعات نامعتبر است.']);
    exit;
}


// خطای اعتبارسنجی رو جمع می‌کنیم تا همه رو یکجا برگردونیم، نه فقط اولی
$errors = [];

function isCompatibleWithChassis(?string $jsonList, int $chassisId): bool {
    // قانون: NULL یا آرایه‌ی خالی یعنی سازگار با همه‌ی شاسی‌ها
    if (!$jsonList) return true;
    $ids = json_decode($jsonList, true);
    if (!is_array($ids) || empty($ids)) return true;
    return in_array($chassisId, $ids, false);
}

function fetchRow(PDO $pdo, string $table, $id): ?array {
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}


function jsonArray(?string $json): array {
    if (!$json) return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function raidUsableGb(int $capacityGb, int $qty, string $raid): int {
    switch ($raid) {
        case '1': return $qty === 2 ? $capacityGb : 0;
        case '5': return $qty >= 3 ? ($qty - 1) * $capacityGb : 0;
        case '6': return $qty >= 4 ? ($qty - 2) * $capacityGb : 0;
        case '10': return ($qty >= 4 && $qty % 2 === 0) ? (int)(($qty / 2) * $capacityGb) : 0;
        case '50': return $qty >= 6 ? ($qty - 2) * $capacityGb : 0;
        case '60': return $qty >= 8 ? ($qty - 4) * $capacityGb : 0;
        default: return $qty * $capacityGb;
    }
}

function validateRaidQty(string $raid, int $qty): bool {
    switch ($raid) {
        case '0': return $qty >= 2;
        case '1': return $qty === 2;
        case '5': return $qty >= 3;
        case '6': return $qty >= 4;
        case '10': return $qty >= 4 && $qty % 2 === 0;
        case '50': return $qty >= 6;
        case '60': return $qty >= 8;
        case 'none':
        case '': return true;
        default: return false;
    }
}

try {
    $pdo = databaseConnection();

    $target = $request['target'] ?? [];
    $config = $request['config'];

    // ---------------------------------------------------------
    // 1) شاسی
    // ---------------------------------------------------------
    $chassis = fetchRow($pdo, 'Chassis', $config['chassis']['id']);
    if (!$chassis) {
        $errors[] = 'شاسی انتخابی معتبر نیست.';
    }
    $chassisId = $chassis ? (int)$chassis['id'] : null;

    // ---------------------------------------------------------
    // 2) CPU: باید وجود داشته باشه، سوکتش با شاسی بخونه، سازگاری صریح رعایت بشه، تعداد از حد مجاز بیشتر نباشه
    // ---------------------------------------------------------
    $cpu = null;
    $cpuQty = (int)($config['cpuQty'] ?? 0);
    if ($chassis) {
        $cpu = fetchRow($pdo, 'CPUs', $config['cpu']['id'] ?? null);
        if (!$cpu) {
            $errors[] = 'پردازنده انتخابی معتبر نیست.';
        } else {
            if ($cpu['socket_type'] !== $chassis['cpu_socket_type']) {
                $errors[] = 'سوکت پردازنده با شاسی انتخابی سازگار نیست.';
            }
            if (!isCompatibleWithChassis($cpu['compatible_chassis_ids'] ?? null, $chassisId)) {
                $errors[] = 'پردازنده انتخابی برای این شاسی تاییدشده نیست.';
            }
            if ($cpuQty < 1 || $cpuQty > (int)$chassis['max_cpus']) {
                $errors[] = "تعداد پردازنده مجاز نیست (حداکثر {$chassis['max_cpus']} عدد).";
            }
        }
    }

    // ---------------------------------------------------------
    // 3) RAM: باید وجود داشته باشه، نسل حافظه بخونه، با CPU سازگار باشه، تعداد از حد مجاز بیشتر نباشه
    // ---------------------------------------------------------
    $ram = null;
    $ramQty = (int)($config['ramQty'] ?? 0);
    if ($chassis) {
        $ram = fetchRow($pdo, 'RAMs', $config['ram']['id'] ?? null);
        if (!$ram) {
            $errors[] = 'رم انتخابی معتبر نیست.';
        } else {
            if ($ram['memory_generation'] !== $chassis['ram_generation']) {
                $errors[] = 'نسل رم با شاسی انتخابی سازگار نیست.';
            }
            if (!isCompatibleWithChassis($ram['compatible_chassis_ids'] ?? null, $chassisId)) {
                $errors[] = 'رم انتخابی برای این شاسی تاییدشده نیست.';
            }
            if ($cpu) {
                $cpuIds = $ram['compatible_cpu_ids'] ? json_decode($ram['compatible_cpu_ids'], true) : null;
                if (is_array($cpuIds) && !empty($cpuIds) && !in_array((int)$cpu['id'], $cpuIds, false)) {
                    $errors[] = 'رم انتخابی با پردازنده انتخابی سازگار نیست.';
                }
            }
            if ($ramQty < 1 || $ramQty > (int)$chassis['max_ram_slots']) {
                $errors[] = "تعداد رم مجاز نیست (حداکثر {$chassis['max_ram_slots']} عدد).";
            }
        }
    }

    // ---------------------------------------------------------
    // 4) قطعات اختیاری: هرکدوم که انتخاب شده باشه باید وجود داشته باشه و با شاسی سازگار باشه
    // ---------------------------------------------------------
    $optionalSingle = [
        'controller' => ['table' => 'Storage_Controllers', 'label' => 'کنترلر'],
        'gpu'        => ['table' => 'GPUs',                 'label' => 'گرافیک'],
        'riser2'     => ['table' => 'Risers',                'label' => 'رایزر ۲'],
        'riser3'     => ['table' => 'Risers',                'label' => 'رایزر ۳'],
        'psu'        => ['table' => 'Power_Supplies',       'label' => 'پاور'],
    ];
    $fetchedOptional = [];
    if ($chassis) {
        foreach ($optionalSingle as $key => $info) {
            if (!empty($config[$key]['id'])) {
                $row = fetchRow($pdo, $info['table'], $config[$key]['id']);
                if (!$row) {
                    $errors[] = "{$info['label']} انتخابی معتبر نیست.";
                } elseif (!isCompatibleWithChassis($row['compatible_chassis_ids'] ?? null, $chassisId)) {
                    $errors[] = "{$info['label']} انتخابی برای این شاسی تاییدشده نیست.";
                } else {
                    $fetchedOptional[$key] = $row;
                }
            }
        }
    }

    $optionalArrays = [
        'drives'         => ['table' => 'Storage_Drives',      'idField' => 'driveId',   'label' => 'درایو'],
        'networks'       => ['table' => 'Network_Adapters',    'idField' => 'networkId', 'label' => 'کارت شبکه'],
        'hbas'           => ['table' => 'HBAs',                'idField' => 'hbaId',     'label' => 'HBA'],
        'opticalDrives'  => ['table' => 'Optical_Drives',      'idField' => 'opticalId', 'label' => 'دی‌وی‌دی درایو'],
    ];
    $fetchedArrays = [];
    if ($chassis) {
        foreach ($optionalArrays as $key => $info) {
            $fetchedArrays[$key] = [];
            $items = $config[$key] ?? [];
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                $itemId = is_array($item) ? ($item[$info['idField']] ?? $item['id'] ?? null) : $item;
                if (!$itemId) continue;
                $row = fetchRow($pdo, $info['table'], $itemId);
                if (!$row) {
                    $errors[] = "{$info['label']} انتخابی معتبر نیست.";
                } elseif (!isCompatibleWithChassis($row['compatible_chassis_ids'] ?? null, $chassisId)) {
                    $errors[] = "{$info['label']} انتخابی برای این شاسی تاییدشده نیست.";
                } else {
                    $qty = is_array($item) ? (int)($item['qty'] ?? 1) : 1;
                    $fetchedArrays[$key][] = ['row' => $row, 'qty' => max(1, $qty), 'item' => is_array($item) ? $item : []];
                }
            }
        }
    }

    // ---------------------------------------------------------
    // 4b) اعتبارسنجی معماری سخت‌افزار مشابه فرانت‌اند
    // ---------------------------------------------------------
    if ($chassis && $cpu && $ram) {
        $storageRules = json_decode($chassis['storage_rules'] ?? '{}', true) ?: [];
        $maxBays = (int)($storageRules['max_bays'] ?? 24);
        $baseDriveType = $storageRules['base_drive_type'] ?? null;
        $totalDriveBays = 0;
        $totalUsableStorageGb = 0;
        $requiresHwRaid = false;

        foreach ($fetchedArrays['drives'] as $driveItem) {
            $drive = $driveItem['row'];
            $qty = (int)$driveItem['qty'];
            $raid = (string)($driveItem['item']['raid'] ?? 'none');
            $totalDriveBays += $qty;

            if ($baseDriveType && $drive['form_factor'] !== $baseDriveType) {
                $errors[] = "فرم‌فاکتور درایو {$drive['model_name']} با شاسی انتخابی سازگار نیست.";
            }
            if (!validateRaidQty($raid, $qty)) {
                $errors[] = "تعداد دیسک برای RAID {$raid} معتبر نیست.";
            }
            if (in_array($raid, ['5', '6', '50', '60'], true)) {
                $requiresHwRaid = true;
            }
            $totalUsableStorageGb += raidUsableGb((int)$drive['capacity_gb'], $qty, $raid);
        }

        if ($totalDriveBays > $maxBays) {
            $errors[] = "تعداد کل درایوها ({$totalDriveBays}) از ظرفیت شاسی ({$maxBays}) بیشتر است.";
        }

        if (!empty($target['storage']) && $totalUsableStorageGb < (int)$target['storage']) {
            $errors[] = 'ظرفیت قابل استفاده ذخیره‌سازی از هدف تعیین‌شده کمتر است.';
        }

        $activeController = $fetchedOptional['controller'] ?? null;
        $activeControllerName = $activeController['model_name'] ?? ($chassis['default_controller'] ?? 'S100i');
        $isHardwareController = preg_match('/(P|E|MR)\d{3}/i', $activeControllerName) === 1;
        if ($requiresHwRaid && !$isHardwareController && empty($config['sasExpander'])) {
            $errors[] = 'برای RAID پیشرفته باید کنترلر سخت‌افزاری معتبر یا SAS Expander انتخاب شود.';
        }

        $controllerLimit = $activeController ? (int)($activeController['max_drives'] ?? 8) : (strpos($activeControllerName, 'S100i') !== false ? 14 : 8);
        if (!empty($config['sasExpander'])) $controllerLimit = 999;
        if ($controllerLimit > 0 && $totalDriveBays > $controllerLimit) {
            $errors[] = "تعداد درایوها از ظرفیت کنترلر فعال ({$controllerLimit}) بیشتر است.";
        }

        if ($ramQty > ((int)$cpuQty * (int)($chassis['ram_slots_per_cpu'] ?? 12))) {
            $errors[] = 'تعداد رم از ظرفیت اسلات‌های فعال بر اساس تعداد پردازنده بیشتر است.';
        }

        $gpuQty = isset($fetchedOptional['gpu']) ? max(1, (int)($config['gpuQty'] ?? 1)) : 0;
        if (!empty($target['gpu']) && $gpuQty < 1) {
            $errors[] = 'بر اساس نیاز کاربر، انتخاب حداقل یک GPU الزامی است.';
        }

        if (isset($fetchedOptional['riser3']) && $cpuQty < 2) {
            $errors[] = 'رایزر سوم نیازمند پردازنده دوم است.';
        }

        $availX16Gpu = 0;
        $availGeneral = (int)($chassis['base_x8_slots'] ?? 0) + (int)($chassis['base_x16_slots'] ?? 0);
        foreach (['riser2', 'riser3'] as $riserKey) {
            if (!isset($fetchedOptional[$riserKey])) continue;
            if ($riserKey === 'riser3' && $cpuQty < 2) continue;
            $riser = $fetchedOptional[$riserKey];
            $x16 = (int)($riser['x16_slots'] ?? 0);
            $total = (int)($riser['total_slots'] ?? 0);
            $availX16Gpu += $x16;
            $availGeneral += max(0, $total - $x16);
        }

        $reqGeneral = !empty($config['sasExpander']) ? 1 : 0;
        if ($activeController && (int)($activeController['pcie_slots_used'] ?? 0) > 0) $reqGeneral += (int)$activeController['pcie_slots_used'];
        foreach ($fetchedArrays['hbas'] as $hba) $reqGeneral += (int)($hba['row']['pcie_slots_used'] ?? 1) * (int)$hba['qty'];

        $flrCount = 0;
        foreach ($fetchedArrays['networks'] as $net) {
            if (($net['row']['form_factor'] ?? '') === 'FlexibleLOM') $flrCount += (int)$net['qty'];
            else $reqGeneral += (int)($net['row']['pcie_slots_used'] ?? 1) * (int)$net['qty'];
        }
        if ($flrCount > 1) {
            $errors[] = 'شاسی فقط یک جایگاه FlexibleLOM دارد.';
        }

        if (isset($fetchedOptional['gpu'])) {
            $gpuSlotsUsed = (int)($fetchedOptional['gpu']['pcie_slots_used'] ?? 1);
            if ($gpuQty > $availX16Gpu) {
                $errors[] = 'تعداد GPU از اسلات‌های x16 قابل استفاده بیشتر است.';
            }
            $reqGeneral += max(0, $gpuSlotsUsed - 1) * $gpuQty;
        }
        $remainingGpuSlots = max(0, $availX16Gpu - $gpuQty);
        if ($reqGeneral > ($availGeneral + $remainingGpuSlots)) {
            $errors[] = 'اسلات‌های PCIe برای کارت‌های جانبی کافی نیست.';
        }
    }

    // ---------------------------------------------------------
    // اگر خطایی جمع شده، اصلاً ذخیره نکن
    // ---------------------------------------------------------
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'خطا در اعتبارسنجی پیکربندی.', 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------
    // 5) محاسبه‌ی توان مصرفی روی سرور (به کلاینت اعتماد نمی‌کنیم)
    // ---------------------------------------------------------
    $totalWatts = (int)($chassis['base_power_watts'] ?? 100); // پایه چاسی/فن‌ها/مادربرد (اختصاصی هر شاسی)
    $totalWatts += (int)$cpu['tdp_watts'] * $cpuQty;
    $totalWatts += (int)$ram['power_consumption_watts'] * $ramQty;
    if (isset($fetchedOptional['gpu'])) {
        $gpuQty = max(1, (int)($config['gpuQty'] ?? 1));
        $totalWatts += (int)$fetchedOptional['gpu']['tdp_watts'] * $gpuQty;
    }
    foreach ($fetchedArrays['drives'] as $d) {
        $totalWatts += (int)$d['row']['power_consumption_watts'] * $d['qty'];
    }
    foreach ($fetchedArrays['opticalDrives'] as $o) {
        $totalWatts += (int)$o['row']['power_consumption_watts'] * $o['qty'];
    }
    // شبکه/کنترلر/HBA مصرف قابل‌توجهی ندارن و در محاسبه‌ی فعلی فرانت‌اند هم لحاظ نمی‌شن

    if (isset($fetchedOptional['psu'])) {
        $psuQty = max(1, (int)($config['psuQty'] ?? ($chassis['max_psu_bays'] ?? 2)));
        $safeWatts = (int)ceil($totalWatts * 1.20);
        $availableWatts = (int)$fetchedOptional['psu']['wattage'] * $psuQty;
        if ($availableWatts < $safeWatts) {
            $errors[] = "توان پاور انتخابی ({$availableWatts}W) کمتر از حد ایمن مورد نیاز ({$safeWatts}W) است.";
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'خطا در اعتبارسنجی پیکربندی.', 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------------------------------------------------------
    // 5b) محاسبه‌ی قیمت کل (اگه قیمت قطعه NULL باشه، یعنی «هنوز قیمت‌گذاری نشده»؛
    //     چنین موردی کل مبلغ رو نامعتبر می‌کنه چون نمی‌شه پیش‌فاکتور ناقص داد)
    // ---------------------------------------------------------
    $totalPrice = 0;
    $priceIncomplete = false;
    $addPrice = function($row, $qty = 1) use (&$totalPrice, &$priceIncomplete) {
        if ($row['price'] === null) { $priceIncomplete = true; return; }
        $totalPrice += (float)$row['price'] * $qty;
    };
    $addPrice($chassis);
    $addPrice($cpu, $cpuQty);
    $addPrice($ram, $ramQty);
    foreach (['controller', 'gpu', 'riser2', 'riser3', 'psu'] as $key) {
        if (isset($fetchedOptional[$key])) {
            $qty = 1;
            if ($key === 'gpu') $qty = max(1, (int)($config['gpuQty'] ?? 1));
            if ($key === 'psu') $qty = max(1, (int)($config['psuQty'] ?? ($chassis['max_psu_bays'] ?? 2)));
            $addPrice($fetchedOptional[$key], $qty);
        }
    }
    foreach (['drives', 'networks', 'hbas', 'opticalDrives'] as $key) {
        foreach ($fetchedArrays[$key] as $item) $addPrice($item['row'], $item['qty']);
    }
    $totalPrice = $priceIncomplete ? null : round($totalPrice, 2);

    // ---------------------------------------------------------
    // 6) ذخیره‌سازی
    // ---------------------------------------------------------
    $tracking_code = 'HPE-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $selected_components = [
        'chassis_id'   => $chassisId,
        'cpu'          => ['id' => (int)$cpu['id'], 'qty' => $cpuQty],
        'ram'          => ['id' => (int)$ram['id'], 'qty' => $ramQty],
        'drives'       => array_map(fn($d) => ['id' => (int)$d['row']['id'], 'qty' => $d['qty'], 'raid' => (string)($d['item']['raid'] ?? 'none')], $fetchedArrays['drives']),
        'controller'   => isset($fetchedOptional['controller']) ? ['id' => (int)$fetchedOptional['controller']['id']] : null,
        'sas_expander' => isset($config['sasExpander']) && (bool)$config['sasExpander'],
        'gpu'          => isset($fetchedOptional['gpu']) ? ['id' => (int)$fetchedOptional['gpu']['id'], 'qty' => max(1, (int)($config['gpuQty'] ?? 1))] : null,
        'networks'     => array_map(fn($n) => ['id' => (int)$n['row']['id'], 'qty' => $n['qty']], $fetchedArrays['networks']),
        'riser2'       => isset($fetchedOptional['riser2']) ? ['id' => (int)$fetchedOptional['riser2']['id']] : null,
        'riser3'       => isset($fetchedOptional['riser3']) ? ['id' => (int)$fetchedOptional['riser3']['id']] : null,
        'psu'          => isset($fetchedOptional['psu']) ? ['id' => (int)$fetchedOptional['psu']['id'], 'qty' => max(1, (int)($config['psuQty'] ?? ($chassis['max_psu_bays'] ?? 2)))] : null,
        'fan_type'     => !empty($config['reqHighPerfFan']) ? 'High Performance' : 'Standard',
        'hbas'         => array_map(fn($h) => ['id' => (int)$h['row']['id'], 'qty' => $h['qty']], $fetchedArrays['hbas']),
        'optical_drives' => array_map(fn($o) => ['id' => (int)$o['row']['id'], 'qty' => $o['qty']], $fetchedArrays['opticalDrives']),
    ];

    $sql = "INSERT INTO `User_Configurations`
            (`session_id`, `workload_type`, `min_cores`, `min_ram_gb`, `min_storage_gb`, `selected_components`, `total_power`, `total_price`, `status`)
            VALUES (:session_id, :workload, :cores, :ram, :storage, :components, :power, :price, 'Completed')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':session_id' => $tracking_code,
        ':workload'   => $target['usecase'] ?? 'Custom',
        ':cores'      => $target['cores'] ?? null,
        ':ram'        => $target['ram'] ?? null,
        ':storage'    => $target['storage'] ?? null,
        ':components' => json_encode($selected_components, JSON_UNESCAPED_UNICODE),
        ':power'      => $totalWatts,
        ':price'      => $totalPrice
    ]);

    echo json_encode(['status' => 'success', 'tracking_code' => $tracking_code, 'total_power' => $totalWatts, 'total_price' => $totalPrice, 'price_incomplete' => $priceIncomplete], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
