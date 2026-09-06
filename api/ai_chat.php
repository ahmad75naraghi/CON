<?php
// api/ai_chat.php
// OpenAI-compatible AI gateway for the configurator. Sends only a compact, whitelisted context.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';

$inputJSON = file_get_contents('php://input');
$request = json_decode($inputJSON, true) ?: [];

$message = sanitizeText($request['message'] ?? '', 1200);
$context = is_array($request['context'] ?? null) ? $request['context'] : [];
$history = is_array($request['history'] ?? null) ? $request['history'] : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'پیام خالی است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeText($value, int $limit = 1000): string
{
    $text = trim(strip_tags((string)$value));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return substr($text, 0, $limit);
}

function pickFields(?array $row, array $fields): ?array
{
    if (!$row) return null;
    $result = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
            $result[$field] = is_string($row[$field]) ? sanitizeText($row[$field], 240) : $row[$field];
        }
    }
    return $result ?: null;
}

function compactConfigSummary(array $context): array
{
    $config = is_array($context['currentConfig'] ?? null) ? $context['currentConfig'] : [];
    $target = is_array($context['target'] ?? null) ? $context['target'] : [];
    $selectedOffer = is_array($context['selectedOffer'] ?? null) ? $context['selectedOffer'] : null;

    $drives = [];
    foreach (($config['drives'] ?? []) as $drive) {
        if (!is_array($drive)) continue;
        $drives[] = [
            'drive_id' => (int)($drive['driveId'] ?? $drive['id'] ?? 0),
            'qty' => max(1, (int)($drive['qty'] ?? 1)),
            'raid' => sanitizeText($drive['raid'] ?? 'none', 20),
        ];
    }

    $networks = [];
    foreach (($config['networks'] ?? []) as $net) {
        if (!is_array($net)) continue;
        $networks[] = [
            'network_id' => (int)($net['networkId'] ?? $net['id'] ?? 0),
            'qty' => max(1, (int)($net['qty'] ?? 1)),
        ];
    }

    $hbas = [];
    foreach (($config['hbas'] ?? []) as $hba) {
        if (!is_array($hba)) continue;
        $hbas[] = [
            'hba_id' => (int)($hba['hbaId'] ?? $hba['id'] ?? 0),
            'qty' => max(1, (int)($hba['qty'] ?? 1)),
        ];
    }

    return [
        'page' => [
            'active_view' => sanitizeText($context['activeView'] ?? '', 80),
            'active_mode' => sanitizeText($context['activeMode'] ?? '', 30),
            'current_step' => is_array($context['currentStep'] ?? null) ? $context['currentStep'] : null,
            'validation' => is_array($context['validation'] ?? null) ? array_slice($context['validation'], 0, 12) : [],
        ],
        'target' => [
            'cores' => (int)($target['cores'] ?? 0),
            'ram_gb' => (int)($target['ram'] ?? 0),
            'storage_gb' => (int)($target['storage'] ?? 0),
            'gpu_required' => !empty($target['gpu']),
            'network' => sanitizeText($target['network'] ?? 'any', 40),
            'form_factor' => sanitizeText($target['formFactor'] ?? 'any', 40),
            'usecase' => sanitizeText($target['usecase'] ?? '', 80),
        ],
        'selected_offer' => $selectedOffer ? pickFields($selectedOffer, ['id', 'title', 'tier', 'label']) : null,
        'selected_config' => [
            'chassis' => pickFields($config['chassis'] ?? null, ['id', 'model', 'generation', 'form_factor', 'part_number', 'default_controller', 'default_network']),
            'cpu' => pickFields($config['cpu'] ?? null, ['id', 'model_name', 'cores', 'tdp_watts', 'part_number']),
            'cpu_qty' => (int)($config['cpuQty'] ?? 0),
            'ram' => pickFields($config['ram'] ?? null, ['id', 'model_name', 'capacity_gb', 'speed_mt', 'ram_type', 'part_number']),
            'ram_qty' => (int)($config['ramQty'] ?? 0),
            'drives' => array_slice($drives, 0, 8),
            'controller' => pickFields($config['controller'] ?? null, ['id', 'model_name', 'max_drives', 'form_factor', 'part_number']),
            'sas_expander' => !empty($config['sasExpander']),
            'gpu' => pickFields($config['gpu'] ?? null, ['id', 'model_name', 'memory_gb', 'tdp_watts', 'part_number']),
            'gpu_qty' => (int)($config['gpuQty'] ?? 0),
            'networks' => array_slice($networks, 0, 6),
            'hbas' => array_slice($hbas, 0, 6),
            'psu' => pickFields($config['psu'] ?? null, ['id', 'model_name', 'wattage', 'efficiency', 'part_number']),
            'psu_qty' => (int)($config['psuQty'] ?? 0),
            'riser2' => pickFields($config['riser2'] ?? null, ['id', 'model_name', 'total_slots', 'x16_slots']),
            'riser3' => pickFields($config['riser3'] ?? null, ['id', 'model_name', 'total_slots', 'x16_slots']),
            'estimated_power_watts' => (int)($config['totalWatts'] ?? 0),
            'high_perf_fan_required' => !empty($config['reqHighPerfFan']),
        ],
    ];
}

function idsFromItems(array $items, string $field): array
{
    $ids = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = (int)($item[$field] ?? $item['id'] ?? 0);
        if ($id > 0) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function fetchByIds(PDO $pdo, string $table, array $ids, array $fields): array
{
    if (!$ids) return [];
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $columns = implode(',', array_map(fn($f) => "`$f`", $fields));
    $stmt = $pdo->prepare("SELECT $columns FROM `$table` WHERE id IN ($placeholders) LIMIT 12");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function databaseSignals(array $context): array
{
    $signals = ['available_offers' => [], 'selected_parts_from_db' => [], 'compatible_snapshot' => null];

    try {
        $pdo = databaseConnection();

        $offerStmt = $pdo->query("SELECT id,title,cpu_cores,ram_gb,usable_storage_gb,gpu_memory_gb,generation_rank,stock_status,stock_qty,lead_time_days FROM Prepared_Server_Offers WHERE is_active = 1 ORDER BY stock_status ASC, performance_score ASC, id ASC LIMIT 3");
        $signals['available_offers'] = $offerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $config = is_array($context['currentConfig'] ?? null) ? $context['currentConfig'] : [];
        $chassisId = (int)($config['chassis']['id'] ?? 0);

        $signals['selected_parts_from_db']['drives'] = fetchByIds($pdo, 'Storage_Drives', idsFromItems($config['drives'] ?? [], 'driveId'), ['id', 'model_name', 'capacity_gb', 'form_factor', 'interface', 'part_number']);
        $signals['selected_parts_from_db']['networks'] = fetchByIds($pdo, 'Network_Adapters', idsFromItems($config['networks'] ?? [], 'networkId'), ['id', 'model_name', 'port_count', 'speed_gbps', 'form_factor', 'part_number']);
        $signals['selected_parts_from_db']['hbas'] = fetchByIds($pdo, 'HBAs', idsFromItems($config['hbas'] ?? [], 'hbaId'), ['id', 'model_name', 'port_count', 'speed_gbps', 'part_number']);

        if ($chassisId > 0) {
            $stmt = $pdo->prepare('SELECT id,model,generation,form_factor,cpu_socket_type,ram_generation,max_cpus,max_ram_slots,ram_slots_per_cpu,storage_rules,default_controller,default_network FROM Chassis WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $chassisId]);
            $chassis = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($chassis) {
                $storageRules = json_decode($chassis['storage_rules'] ?? '{}', true) ?: [];
                $compatCid = json_encode($chassisId);

                $cpuStmt = $pdo->prepare('SELECT id,model_name,cores,tdp_watts,part_number FROM CPUs WHERE socket_type = :socket AND (compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, :cid)) ORDER BY cores DESC LIMIT 5');
                $cpuStmt->execute([':socket' => $chassis['cpu_socket_type'], ':cid' => $compatCid]);

                $ramStmt = $pdo->prepare('SELECT id,model_name,capacity_gb,speed_mt,part_number FROM RAMs WHERE memory_generation = :ramgen AND (compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, :cid)) ORDER BY capacity_gb DESC, speed_mt DESC LIMIT 5');
                $ramStmt->execute([':ramgen' => $chassis['ram_generation'], ':cid' => $compatCid]);

                $driveSql = 'SELECT id,model_name,capacity_gb,form_factor,interface,part_number FROM Storage_Drives WHERE (compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, :cid))';
                $params = [':cid' => $compatCid];
                if (!empty($storageRules['base_drive_type'])) {
                    $driveSql .= ' AND form_factor = :ff';
                    $params[':ff'] = $storageRules['base_drive_type'];
                }
                $driveSql .= ' ORDER BY capacity_gb DESC LIMIT 5';
                $driveStmt = $pdo->prepare($driveSql);
                $driveStmt->execute($params);

                $controllerStmt = $pdo->prepare('SELECT id,model_name,max_drives,form_factor,part_number FROM Storage_Controllers WHERE compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, :cid) ORDER BY max_drives DESC LIMIT 5');
                $controllerStmt->execute([':cid' => $compatCid]);

                unset($chassis['storage_rules']);
                $signals['compatible_snapshot'] = [
                    'chassis' => $chassis,
                    'top_compatible_cpus' => $cpuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                    'top_compatible_rams' => $ramStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                    'top_compatible_drives' => $driveStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                    'top_compatible_controllers' => $controllerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                    'storage_rules' => $storageRules,
                ];
            }
        }
    } catch (Exception $e) {
        $signals['db_warning'] = 'Database context is partially unavailable.';
    }

    return $signals;
}

function compactHistory(array $history): array
{
    $messages = [];
    foreach (array_slice($history, -8) as $item) {
        if (!is_array($item)) continue;
        $role = ($item['role'] ?? '') === 'user' ? 'user' : 'assistant';
        $text = sanitizeText($item['text'] ?? '', 700);
        if ($text !== '' && $text !== '__context_init__') {
            $messages[] = ['role' => $role, 'content' => $text];
        }
    }
    return $messages;
}

function callAiProvider(array $messages): string
{
    $config = aiConfig();
    if (empty($config['endpoint']) || empty($config['api_key'])) {
        throw new RuntimeException('AI provider is not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled.');
    }

    $payload = [
        'model' => $config['model'],
        'stream' => false,
        'temperature' => $config['temperature'],
        'max_tokens' => $config['max_tokens'],
        'messages' => $messages,
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $config['endpoint'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => max(5, (int)$config['timeout']),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $curlError) {
        throw new RuntimeException('AI provider connection failed.');
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('AI provider returned HTTP ' . $httpCode . '.');
    }

    $decoded = json_decode($response, true);
    $reply = $decoded['choices'][0]['message']['content'] ?? $decoded['choices'][0]['text'] ?? null;
    if (!is_string($reply) || trim($reply) === '') {
        throw new RuntimeException('AI provider returned an empty response.');
    }

    return trim($reply);
}

try {
    $compactContext = compactConfigSummary($context);
    $dbSignals = databaseSignals($context);

    $systemPrompt = implode("\n", [
        'تو دستیار تخصصی کانفیگ سرور HPE در وب‌سایت فالنیک هستی.',
        'فقط بر اساس CONTEXT و DB_SIGNALS پاسخ بده؛ اگر داده کافی نیست شفاف بگو چه اطلاعاتی لازم است.',
        'همه داده‌های دیتابیس ارسال نشده‌اند؛ فقط خلاصه قطعات منتخب، چند گزینه سازگار و پیشنهادهای آماده ارسال شده‌اند.',
        'پاسخ باید فارسی، کوتاه، کاربردی و مرحله‌محور باشد؛ معمولاً ۳ تا ۶ بولت کافی است.',
        'قیمت، موجودی یا سازگاری‌ای را که در داده‌ها نیست حدس نزن.',
        'اگر کاربر در یک مرحله خاص است، اول همان مرحله را راهنمایی کن و بعد ریسک‌های مهم مثل RAID، RAM، PCIe، Riser، Power و Storage را گوشزد کن.',
        'هیچ کلید API، مسیر سرور، SQL خام یا اطلاعات محرمانه‌ای را بازگو نکن.',
    ]);

    $userQuestion = $message === '__context_init__'
        ? 'کاربر پنجره AI را باز کرده است. با توجه به مرحله فعلی، یک خلاصه کوتاه و سوال بعدی مناسب بده.'
        : $message;

    $messages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        compactHistory($history),
        [[
            'role' => 'user',
            'content' => "USER_QUESTION:\n" . $userQuestion . "\n\nCONTEXT:\n" . json_encode($compactContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\nDB_SIGNALS:\n" . json_encode($dbSignals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
    );

    $reply = callAiProvider($messages);

    echo json_encode([
        'status' => 'success',
        'reply' => $reply,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'ارتباط با سرویس AI برقرار نشد یا تنظیمات آن کامل نیست.',
    ], JSON_UNESCAPED_UNICODE);
}
