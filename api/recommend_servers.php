<?php
// api/recommend_servers.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$inputJSON = file_get_contents('php://input');
$request = json_decode($inputJSON, true) ?: [];

$target = $request['target'] ?? [];
$answers = $request['answers'] ?? [];


$jsonFieldsMap = [
    'Chassis'             => ['storage_rules', 'cooling_rules'],
    'CPUs'                => ['cooling_requirements', 'compatible_chassis_ids'],
    'RAMs'                => ['compatible_cpu_ids', 'compatible_chassis_ids'],
    'Storage_Drives'      => ['compatible_chassis_ids'],
    'Storage_Controllers' => ['supported_interfaces', 'compatible_chassis_ids'],
    'Network_Adapters'    => ['compatible_chassis_ids'],
    'Power_Supplies'      => ['input_voltage_support', 'compatible_chassis_ids'],
    'GPUs'                => ['compatible_chassis_ids'],
    'Risers'              => ['compatible_chassis_ids'],
    'HBAs'                => ['compatible_chassis_ids'],
    'Optical_Drives'      => ['compatible_chassis_ids'],
];

function decodeJsonRow(?array $row, string $table, array $jsonFieldsMap): ?array
{
    if (!$row) return null;
    foreach ($jsonFieldsMap[$table] ?? [] as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
            $decoded = json_decode($row[$field], true);
            $row[$field] = $decoded === null ? $row[$field] : $decoded;
        }
    }
    return $row;
}

function fetchRow(PDO $pdo, string $table, $id, array $jsonFieldsMap): ?array
{
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return decodeJsonRow($row ?: null, $table, $jsonFieldsMap);
}

function normalizeTarget(array $target, array $answers): array
{
    $services = $answers['1'] ?? $answers[1] ?? [];
    $users = (int)(($answers['2'][0] ?? $answers[2][0] ?? 1));
    $perf = $answers['3'][0] ?? $answers[3][0] ?? 'med';
    $wantsGrowth = (($answers['4'][0] ?? $answers[4][0] ?? 'no') === 'yes');
    $localStorage = (($answers['5'][0] ?? $answers[5][0] ?? 'no') === 'no');
    $infrastructure = $answers['6'] ?? $answers[6] ?? [];

    $cores = (int)($target['cores'] ?? 0);
    $ram = (int)($target['ram'] ?? 0);
    $storage = (int)($target['storage'] ?? 0);
    $gpu = !empty($target['gpu']);
    $usecase = $target['usecase'] ?? 'Guidance';

    if ($cores <= 1 || $ram <= 1) {
        $cores = 8 * max(1, $users);
        $ram = 32 * max(1, $users);
        $storage = $localStorage ? 2000 * max(1, $users) : 500;
    }

    if (in_array('db', $services, true)) { $cores += 8; $ram += 64; $storage += $localStorage ? 1000 : 500; $usecase = 'Database'; }
    if (in_array('virt', $services, true)) { $cores += 8; $ram += 96; $usecase = 'Virtualization'; }
    if (in_array('ai', $services, true)) { $cores += 8; $ram += 64; $gpu = true; $usecase = 'AI'; }
    if (in_array('storage', $services, true)) { $storage += 4000; $usecase = 'File Server'; }
    if (in_array('web', $services, true)) { $cores += 4; $ram += 16; }
    if (in_array('accounting', $services, true) || in_array('crm', $services, true)) { $ram += 16; }

    if ($perf === 'max') { $cores = (int)ceil($cores * 1.5); $ram = (int)ceil($ram * 1.5); $storage = (int)ceil($storage * 1.25); }
    if ($perf === 'min') { $cores = (int)ceil($cores * 0.75); $ram = (int)ceil($ram * 0.75); }
    if ($wantsGrowth) { $cores = (int)ceil($cores * 1.25); $ram = (int)ceil($ram * 1.25); $storage = (int)ceil($storage * 1.25); }

    return [
        'usecase' => $usecase,
        'services' => array_values($services),
        'user_factor' => max(1, $users),
        'performance' => $perf,
        'wants_growth' => $wantsGrowth,
        'cores' => max(4, $cores),
        'ram' => max(16, $ram),
        'storage' => max(500, $storage),
        'gpu' => $gpu,
        'network' => in_array('fiber', $infrastructure, true) ? 'fiber' : 'any',
        'formFactor' => in_array('rack', $infrastructure, true) ? 'rack' : 'any',
    ];
}

function fallbackOffers(): array
{
    return json_decode('[{"id":1,"title":"DL360 Gen9 اقتصادی","description":"کم‌هزینه‌ترین سرور آماده‌ای که حداقل نیازهای پایه را پوشش می‌دهد و برای شروع کار مناسب است.","icon":"🖨️","cpu_cores":12,"ram_gb":64,"usable_storage_gb":2000,"raw_storage_gb":4000,"gpu_memory_gb":0,"generation_rank":9,"expansion_score":35,"performance_score":12064,"bullets":["ضعیف‌ترین گزینه‌ای که کار را راه می‌اندازد","مناسب سرویس‌های عمومی، حسابداری، CRM و تیم‌های کوچک","مصرف توان و هزینه اولیه کمتر"],"selected_components":{"chassis_id":3,"cpu":{"id":55,"qty":1},"ram":{"id":20,"qty":2},"drives":[{"id":127,"qty":2,"raid":"1"}],"controller":null,"sas_expander":false,"gpu":null,"networks":[],"riser2":null,"riser3":null,"psu":{"id":13,"qty":2},"hbas":[],"optical_drives":[]}},{"id":2,"title":"DL380 Gen10 مدیریت‌شده","description":"ترکیب استاندارد و متعادل با پردازنده دوگانه، رم بیشتر، RAID سخت‌افزاری و فضای توسعه مناسب برای چند سال آینده.","icon":"🏢","cpu_cores":32,"ram_gb":256,"usable_storage_gb":3840,"raw_storage_gb":7680,"gpu_memory_gb":0,"generation_rank":10,"expansion_score":70,"performance_score":32256,"bullets":["تعادل خوب بین هزینه، کارایی و پایداری","فضای توسعه مناسب برای رشد سازمان","مناسب دیتابیس، مجازی‌سازی سبک و سرویس‌های سازمانی"],"selected_components":{"chassis_id":1,"cpu":{"id":25,"qty":2},"ram":{"id":5,"qty":4},"drives":[{"id":31,"qty":4,"raid":"10"}],"controller":{"id":2},"sas_expander":false,"gpu":null,"networks":[{"id":2,"qty":1}],"riser2":{"id":3},"riser3":null,"psu":{"id":3,"qty":2},"hbas":[],"optical_drives":[]}},{"id":3,"title":"ML110 Gen11 پیشرفته","description":"گزینه نسل جدیدتر با ظرفیت ذخیره‌سازی بسیار بالاتر، NVMe، رم مناسب و GPU برای رشد آینده و بارهای سنگین‌تر.","icon":"🚀","cpu_cores":32,"ram_gb":256,"usable_storage_gb":25600,"raw_storage_gb":51200,"gpu_memory_gb":24,"generation_rank":11,"expansion_score":85,"performance_score":32256,"bullets":["نسل جدیدتر و مناسب‌تر برای ارتقای آینده","ظرفیت ذخیره‌سازی چندبرابر نیازهای معمول","آماده برای GPU، AI سبک، VDI یا بارهای پیشرفته"],"selected_components":{"chassis_id":2,"cpu":{"id":37,"qty":1},"ram":{"id":14,"qty":4},"drives":[{"id":76,"qty":4,"raid":"10"}],"controller":{"id":12},"sas_expander":false,"gpu":{"id":7,"qty":1},"networks":[{"id":3,"qty":1}],"riser2":{"id":18},"riser3":null,"psu":{"id":11,"qty":2},"hbas":[],"optical_drives":[]}}]', true) ?: [];
}

function getCatalogOffers(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT * FROM `Prepared_Server_Offers` WHERE is_active = 1 ORDER BY performance_score ASC, generation_rank ASC, id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return fallbackOffers();

        return array_map(function ($row) {
            $row['cpu_cores'] = (int)$row['cpu_cores'];
            $row['ram_gb'] = (int)$row['ram_gb'];
            $row['usable_storage_gb'] = (int)$row['usable_storage_gb'];
            $row['raw_storage_gb'] = (int)$row['raw_storage_gb'];
            $row['gpu_memory_gb'] = (int)$row['gpu_memory_gb'];
            $row['generation_rank'] = (int)$row['generation_rank'];
            $row['expansion_score'] = (int)$row['expansion_score'];
            $row['performance_score'] = (int)$row['performance_score'];
            $row['stock_status'] = $row['stock_status'] ?? 'Available';
            $row['stock_qty'] = (int)($row['stock_qty'] ?? 1);
            $row['lead_time_days'] = (int)($row['lead_time_days'] ?? 0);
            $row['bullets'] = json_decode($row['bullets'] ?? '[]', true) ?: [];
            $row['selected_components'] = json_decode($row['selected_components'] ?? '{}', true) ?: [];
            return $row;
        }, $rows);
    } catch (Exception $e) {
        return fallbackOffers();
    }
}

function isAvailableOffer(array $offer): bool
{
    return ($offer['stock_status'] ?? 'Available') !== 'Unavailable' && (int)($offer['stock_qty'] ?? 1) > 0;
}

function offerHasFiberNetwork(array $offer): bool
{
    $encoded = json_encode($offer['selected_components'] ?? []);
    // Network part numbers are hydrated later; for catalog scoring we use selected network presence as a soft signal.
    return strpos($encoded ?: '', 'network') !== false || strpos(strtolower($offer['title'] ?? ''), 'fiber') !== false;
}

function meetsTarget(array $offer, array $target, float $multiplier = 1.0): bool
{
    if (!isAvailableOffer($offer)) return false;
    if ($offer['cpu_cores'] < ($target['cores'] * $multiplier)) return false;
    if ($offer['ram_gb'] < ($target['ram'] * $multiplier)) return false;
    if ($target['storage'] > 0 && $offer['usable_storage_gb'] < ($target['storage'] * $multiplier)) return false;
    if (!empty($target['gpu']) && $offer['gpu_memory_gb'] <= 0) return false;
    return true;
}

function workloadBonus(array $offer, array $target): float
{
    $bonus = 0;
    $services = $target['services'] ?? [];

    if (in_array('ai', $services, true) && (int)$offer['gpu_memory_gb'] > 0) $bonus -= 120;
    if (in_array('storage', $services, true) && (int)$offer['usable_storage_gb'] >= (int)$target['storage'] * 1.5) $bonus -= 80;
    if (in_array('virt', $services, true) && (int)$offer['ram_gb'] >= (int)$target['ram'] * 1.25) $bonus -= 70;
    if (in_array('db', $services, true) && (int)$offer['raw_storage_gb'] > 0) $bonus -= 45;
    if (($target['network'] ?? 'any') === 'fiber' && offerHasFiberNetwork($offer)) $bonus -= 25;
    if (!empty($target['wants_growth']) && (int)$offer['expansion_score'] >= 70) $bonus -= 60;

    $stockStatus = $offer['stock_status'] ?? 'Available';
    if ($stockStatus === 'Limited') $bonus += 20;
    $bonus += min(60, max(0, (int)($offer['lead_time_days'] ?? 0)) * 4);

    return $bonus;
}

function scoreDistance(array $offer, array $target, float $multiplier): float
{
    return abs($offer['cpu_cores'] - ($target['cores'] * $multiplier))
        + abs(($offer['ram_gb'] - ($target['ram'] * $multiplier)) / 4)
        + abs(($offer['usable_storage_gb'] - (($target['storage'] ?: 1) * $multiplier)) / 250)
        + workloadBonus($offer, $target);
}

function chooseRecommendations(array $offers, array $target): array
{
    $available = array_values(array_filter($offers, fn($o) => isAvailableOffer($o)));
    if (!$available) $available = $offers;

    $eligible = array_values(array_filter($available, fn($o) => meetsTarget($o, $target, 1.0)));
    if (!$eligible) $eligible = $available;

    usort($eligible, fn($a, $b) => [scoreDistance($a, $target, 1.0), $a['performance_score'], $a['ram_gb'], $a['usable_storage_gb']] <=> [scoreDistance($b, $target, 1.0), $b['performance_score'], $b['ram_gb'], $b['usable_storage_gb']]);
    $eco = $eligible[0];

    $managedPool = array_values(array_filter($eligible, fn($o) => $o['id'] != $eco['id'] && (int)$o['expansion_score'] >= 50));
    if (!$managedPool) $managedPool = array_values(array_filter($available, fn($o) => $o['id'] != $eco['id']));
    usort($managedPool, fn($a, $b) => scoreDistance($a, $target, 1.35) <=> scoreDistance($b, $target, 1.35));
    $managed = $managedPool[0] ?? $eco;

    $advancedPool = array_values(array_filter($available, fn($o) => $o['id'] != $eco['id'] && $o['id'] != $managed['id'] && meetsTarget($o, $target, 2.0)));
    if (!$advancedPool) {
        $advancedPool = array_values(array_filter($available, fn($o) => $o['id'] != $eco['id'] && $o['id'] != $managed['id']));
    }
    usort($advancedPool, fn($a, $b) => [scoreDistance($a, $target, 2.0), -$a['generation_rank'], -$a['expansion_score']] <=> [scoreDistance($b, $target, 2.0), -$b['generation_rank'], -$b['expansion_score']]);
    $advanced = $advancedPool[0] ?? $managed;

    $eco['recommendation_tier'] = 'eco';
    $eco['recommendation_label'] = 'اقتصادی';
    $eco['recommendation_reason'] = 'اولین سرور آماده‌ای که نیاز شما را با کمترین منابع اضافه و موجودی قابل سفارش پوشش می‌دهد.';

    $managed['recommendation_tier'] = 'managed';
    $managed['recommendation_label'] = 'مدیریت‌شده';
    $managed['recommendation_reason'] = 'انتخاب استاندارد با کارایی بهتر، ریسک کمتر و فضای توسعه منطقی.';

    $advanced['recommendation_tier'] = 'advanced';
    $advanced['recommendation_label'] = 'پیشرفته';
    $advanced['recommendation_reason'] = 'ظرفیت بالاتر، نسل جدیدتر و مناسب رشد آینده با کمترین نگرانی ارتقا.';

    return [$eco, $managed, $advanced];
}

function hydrateOffer(PDO $pdo, array $offer, array $target, array $jsonFieldsMap): array
{
    $components = $offer['selected_components'] ?? [];

    $chassis = fetchRow($pdo, 'Chassis', $components['chassis_id'] ?? null, $jsonFieldsMap);
    $cpu = fetchRow($pdo, 'CPUs', $components['cpu']['id'] ?? null, $jsonFieldsMap);
    $ram = fetchRow($pdo, 'RAMs', $components['ram']['id'] ?? null, $jsonFieldsMap);
    $controller = fetchRow($pdo, 'Storage_Controllers', $components['controller']['id'] ?? null, $jsonFieldsMap);
    $gpu = fetchRow($pdo, 'GPUs', $components['gpu']['id'] ?? null, $jsonFieldsMap);
    $riser2 = fetchRow($pdo, 'Risers', $components['riser2']['id'] ?? null, $jsonFieldsMap);
    $riser3 = fetchRow($pdo, 'Risers', $components['riser3']['id'] ?? null, $jsonFieldsMap);
    $psu = fetchRow($pdo, 'Power_Supplies', $components['psu']['id'] ?? null, $jsonFieldsMap);

    $drivesConfig = [];
    $drivesDb = [];
    foreach (($components['drives'] ?? []) as $drive) {
        $row = fetchRow($pdo, 'Storage_Drives', $drive['id'] ?? null, $jsonFieldsMap);
        if (!$row) continue;
        $drivesDb[] = $row;
        $drivesConfig[] = ['driveId' => (string)$row['id'], 'qty' => (int)($drive['qty'] ?? 1), 'raid' => (string)($drive['raid'] ?? 'none')];
    }

    $networksConfig = [];
    $networksDb = [];
    foreach (($components['networks'] ?? []) as $network) {
        $row = fetchRow($pdo, 'Network_Adapters', $network['id'] ?? null, $jsonFieldsMap);
        if (!$row) continue;
        $networksDb[] = $row;
        $networksConfig[] = ['networkId' => (string)$row['id'], 'qty' => (int)($network['qty'] ?? 1)];
    }

    $hbasConfig = [];
    $hbasDb = [];
    foreach (($components['hbas'] ?? []) as $hba) {
        $row = fetchRow($pdo, 'HBAs', $hba['id'] ?? null, $jsonFieldsMap);
        if (!$row) continue;
        $hbasDb[] = $row;
        $hbasConfig[] = ['hbaId' => (string)$row['id'], 'qty' => (int)($hba['qty'] ?? 1)];
    }

    $opticalsConfig = [];
    $opticalsDb = [];
    foreach (($components['optical_drives'] ?? []) as $optical) {
        $row = fetchRow($pdo, 'Optical_Drives', $optical['id'] ?? null, $jsonFieldsMap);
        if (!$row) continue;
        $opticalsDb[] = $row;
        $opticalsConfig[] = ['opticalId' => (string)$row['id'], 'qty' => (int)($optical['qty'] ?? 1)];
    }

    $totalWatts = (int)($chassis['base_power_watts'] ?? 100);
    if ($cpu) $totalWatts += (int)$cpu['tdp_watts'] * (int)($components['cpu']['qty'] ?? 1);
    if ($ram) $totalWatts += (int)$ram['power_consumption_watts'] * (int)($components['ram']['qty'] ?? 1);
    if ($gpu) $totalWatts += (int)$gpu['tdp_watts'] * (int)($components['gpu']['qty'] ?? 1);
    foreach ($drivesDb as $idx => $driveRow) $totalWatts += (int)$driveRow['power_consumption_watts'] * (int)$drivesConfig[$idx]['qty'];
    foreach ($opticalsDb as $idx => $optRow) $totalWatts += (int)$optRow['power_consumption_watts'] * (int)$opticalsConfig[$idx]['qty'];

    $config = [
        'chassis' => $chassis,
        'cpu' => $cpu,
        'cpuQty' => (int)($components['cpu']['qty'] ?? 1),
        'ram' => $ram,
        'ramQty' => (int)($components['ram']['qty'] ?? 1),
        'drives' => $drivesConfig,
        'opticalDrives' => $opticalsConfig,
        'controller' => $controller,
        'hbas' => $hbasConfig,
        'gpu' => $gpu,
        'gpuQty' => $gpu ? (int)($components['gpu']['qty'] ?? 1) : 0,
        'networks' => $networksConfig,
        'psu' => $psu,
        'psuQty' => (int)($components['psu']['qty'] ?? ($chassis['max_psu_bays'] ?? 2)),
        'riser2' => $riser2,
        'riser3' => $riser3,
        'sasExpander' => !empty($components['sas_expander']),
        'totalWatts' => $totalWatts,
        'reqHighPerfFan' => !empty($gpu) || array_reduce($drivesDb, fn($carry, $d) => $carry || !empty($d['requires_high_perf_fan']), false),
    ];

    $displayRows = [];
    if ($chassis) $displayRows[] = ['title' => 'شاسی (Chassis)', 'desc' => "HPE ProLiant {$chassis['model']} {$chassis['generation']} ({$chassis['form_factor']})"];
    if ($cpu) $displayRows[] = ['title' => 'پردازنده (CPU)', 'desc' => $config['cpuQty'] . 'X ' . $cpu['model_name'] . ' — ' . ($cpu['cores'] * $config['cpuQty']) . ' Core'];
    if ($ram) $displayRows[] = ['title' => 'حافظه رم (RAM)', 'desc' => $config['ramQty'] . 'X ' . $ram['model_name'] . ' — مجموع ' . $offer['ram_gb'] . 'GB'];
    foreach ($drivesDb as $idx => $driveRow) $displayRows[] = ['title' => 'فضای ذخیره‌سازی (Storage)', 'desc' => $drivesConfig[$idx]['qty'] . 'X ' . $driveRow['model_name'] . ' (RAID: ' . $drivesConfig[$idx]['raid'] . ')'];
    $displayRows[] = ['title' => 'ظرفیت قابل استفاده', 'desc' => round($offer['usable_storage_gb'] / 1000, 1) . ' TB'];
    if ($controller) $displayRows[] = ['title' => 'کنترلر رید (RAID)', 'desc' => $controller['model_name']];
    elseif ($chassis) $displayRows[] = ['title' => 'کنترلر رید (RAID)', 'desc' => $chassis['default_controller'] ?? 'پیش‌فرض مادربرد'];
    if ($gpu) $displayRows[] = ['title' => 'کارت گرافیک (GPU)', 'desc' => $config['gpuQty'] . 'X ' . $gpu['model_name'] . ' — ' . $offer['gpu_memory_gb'] . 'GB VRAM'];
    foreach ($networksDb as $idx => $netRow) $displayRows[] = ['title' => 'کارت شبکه (Network)', 'desc' => $networksConfig[$idx]['qty'] . 'X ' . $netRow['model_name']];
    if ($riser2) $displayRows[] = ['title' => 'رایزر دوم', 'desc' => $riser2['model_name']];
    if ($riser3) $displayRows[] = ['title' => 'رایزر سوم', 'desc' => $riser3['model_name']];
    if ($psu) $displayRows[] = ['title' => 'منبع تغذیه (Power Supply)', 'desc' => $config['psuQty'] . 'X ' . $psu['model_name']];
    $displayRows[] = ['title' => 'توان تقریبی', 'desc' => $totalWatts . ' W'];
    $displayRows[] = ['title' => 'وضعیت موجودی', 'desc' => ($offer['stock_status'] ?? 'Available') . ' — تعداد: ' . (int)($offer['stock_qty'] ?? 1) . ' — زمان تامین: ' . (int)($offer['lead_time_days'] ?? 0) . ' روز'];

    $offer['config'] = $config;
    $offer['display_rows'] = $displayRows;
    $offer['db'] = [
        'chassis' => array_values(array_filter([$chassis])),
        'cpus' => array_values(array_filter([$cpu])),
        'rams' => array_values(array_filter([$ram])),
        'drives' => $drivesDb,
        'controllers' => array_values(array_filter([$controller])),
        'gpus' => array_values(array_filter([$gpu])),
        'networks' => $networksDb,
        'risers' => array_values(array_filter([$riser2, $riser3])),
        'hbas' => $hbasDb,
        'optical_drives' => $opticalsDb,
        'psus' => array_values(array_filter([$psu])),
    ];
    $offer['target'] = $target;

    unset($offer['selected_components']);
    return $offer;
}

try {
    $pdo = databaseConnection();

    $normalizedTarget = normalizeTarget($target, $answers);
    $catalog = getCatalogOffers($pdo);
    $recommendations = chooseRecommendations($catalog, $normalizedTarget);
    $hydrated = array_map(fn($offer) => hydrateOffer($pdo, $offer, $normalizedTarget, $jsonFieldsMap), $recommendations);

    echo json_encode(['status' => 'success', 'target' => $normalizedTarget, 'offers' => $hydrated], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
