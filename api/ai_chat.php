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
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
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


function sanitizeAssocRecursive($value, int $depth = 0)
{
    if ($depth > 3) return null;
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $safeKey = is_string($key) ? sanitizeText($key, 50) : $key;
            $out[$safeKey] = sanitizeAssocRecursive($item, $depth + 1);
        }
        return $out;
    }
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
    return sanitizeText($value, 180);
}

function cleanCustomerReply(string $text): string
{
    $text = sanitizeText($text, 900);
    $text = preg_replace('/\*\*(.*?)\*\*/u', '$1', $text) ?? $text;
    $text = preg_replace('/(^|\s)#{1,6}\s*/u', '$1', $text) ?? $text;
    $text = preg_replace('/\|[^\n]*\|/u', '', $text) ?? $text; // remove markdown tables if provider ignores JSON contract
    $lines = preg_split('/\R/u', $text) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, '|') === 0 || preg_match('/^[-:| ]{5,}$/u', $line)) continue;
        $lineLength = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
        $chunks = $lineLength > 220 ? (preg_split('/(?<=[.!؟])\s+/u', $line) ?: [$line]) : [$line];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || preg_match('/^[-:| ]{5,}$/u', $chunk)) continue;
            $clean[] = $chunk;
            if (count($clean) >= 4) break 2;
        }
    }
    return implode("\n", $clean);
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
            'current_step' => is_array($context['currentStep'] ?? null) ? sanitizeAssocRecursive($context['currentStep']) : null,
            'validation' => array_map(fn($item) => sanitizeAssocRecursive($item), is_array($context['validation'] ?? null) ? array_slice($context['validation'], 0, 12) : []),
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

function compactHistory(array $history, string $currentMessage = ''): array
{
    $messages = [];
    foreach (array_slice($history, -8) as $item) {
        if (!is_array($item)) continue;
        $role = ($item['role'] ?? '') === 'user' ? 'user' : 'assistant';
        $text = sanitizeText($item['text'] ?? '', 700);
        if ($text !== '' && $text !== '__context_init__') {
            if ($role === 'user' && $currentMessage !== '' && $text === $currentMessage) continue;
            $messages[] = ['role' => $role, 'content' => $text];
        }
    }
    return $messages;
}


function decodeAiJson(string $raw): ?array
{
    $text = trim($raw);
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $text) ?? $text;
    $decoded = json_decode($text, true);
    if (is_array($decoded)) return $decoded;

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidate = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) return $decoded;
    }
    return null;
}


function allowedAiActionTypes(): array
{
    return ['set_ram_total', 'set_ram_qty', 'set_cpu_qty', 'set_psu_qty', 'add_drive_raid10', 'set_cpu', 'set_ram', 'set_cpu_ram'];
}

function sanitizeAiAction($item): ?array
{
    if (!is_array($item)) return null;
    $type = sanitizeText($item['type'] ?? '', 50);
    if (!in_array($type, allowedAiActionTypes(), true)) return null;

    $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
    $safePayload = [];
    foreach ($payload as $key => $value) {
        $safeKey = sanitizeText($key, 40);
        if ($safeKey === '') continue;
        if (is_numeric($value)) $safePayload[$safeKey] = (int)$value;
        elseif (is_bool($value)) $safePayload[$safeKey] = $value;
        else $safePayload[$safeKey] = sanitizeText($value, 80);
    }

    $label = sanitizeText($item['label'] ?? '', 70);
    if ($label === '') $label = 'اعمال پیشنهاد';
    return ['label' => $label, 'type' => $type, 'payload' => $safePayload];
}

function buildCpuRamAction(array $compactContext, array $dbSignals, string $focusText): ?array
{
    $focus = function_exists('mb_strtolower') ? mb_strtolower($focusText, 'UTF-8') : strtolower($focusText);
    $isCpuRamIntent = strpos($focus, 'cpu') !== false || strpos($focus, 'پردازنده') !== false || strpos($focus, 'رم') !== false || stripos($focusText, 'ram') !== false;
    $isApplyIntent = strpos($focus, 'اصلاح') !== false || strpos($focus, 'ارتقا') !== false || strpos($focus, 'تغییر') !== false || strpos($focus, 'درست') !== false || strpos($focus, 'اعمال') !== false;
    if (!$isCpuRamIntent || !$isApplyIntent) return null;

    $snapshot = is_array($dbSignals['compatible_snapshot'] ?? null) ? $dbSignals['compatible_snapshot'] : [];
    $cpus = is_array($snapshot['top_compatible_cpus'] ?? null) ? $snapshot['top_compatible_cpus'] : [];
    $rams = is_array($snapshot['top_compatible_rams'] ?? null) ? $snapshot['top_compatible_rams'] : [];
    if (!$cpus && !$rams) return null;

    $target = $compactContext['target'] ?? [];
    $config = $compactContext['selected_config'] ?? [];
    $chassis = is_array($config['chassis'] ?? null) ? $config['chassis'] : [];
    $maxCpus = max(1, (int)($chassis['max_cpus'] ?? 2));
    $maxRamSlots = max(1, (int)($chassis['max_ram_slots'] ?? 24));
    $neededCores = max(1, (int)($target['cores'] ?? 0));
    $neededRam = max(16, (int)($target['ram_gb'] ?? 0));
    $gpu = is_array($config['gpu'] ?? null) ? $config['gpu'] : null;
    if ($gpu) $neededRam = max($neededRam, (int)($gpu['memory_gb'] ?? 0) * max(1, (int)($config['gpu_qty'] ?? 1)) * 2);

    $selectedCpu = null; $selectedCpuQty = max(1, (int)($config['cpu_qty'] ?? 1));
    if ($cpus) {
        $bestScore = PHP_INT_MAX;
        foreach ($cpus as $cpu) {
            $cores = max(1, (int)($cpu['cores'] ?? 1));
            for ($qty = 1; $qty <= $maxCpus; $qty++) {
                $total = $cores * $qty;
                if ($total < $neededCores) continue;
                $score = ($total - $neededCores) + ($qty * 2);
                if ($score < $bestScore) { $bestScore = $score; $selectedCpu = $cpu; $selectedCpuQty = $qty; }
            }
        }
        if (!$selectedCpu) { $selectedCpu = $cpus[0]; $selectedCpuQty = min($maxCpus, 1); }
    }

    $selectedRam = null; $selectedRamQty = max(1, (int)($config['ram_qty'] ?? 1));
    if ($rams) {
        $bestScore = PHP_INT_MAX;
        foreach ($rams as $ram) {
            $cap = max(1, (int)($ram['capacity_gb'] ?? 1));
            $qty = (int)ceil($neededRam / $cap);
            $qty = max(1, min($maxRamSlots, $qty));
            if ($qty * $cap < $neededRam) continue;
            if ($selectedCpuQty > 1 && $qty % $selectedCpuQty !== 0) $qty += $selectedCpuQty - ($qty % $selectedCpuQty);
            if ($qty > $maxRamSlots) continue;
            $score = ($qty * $cap - $neededRam) + $qty;
            if ($score < $bestScore) { $bestScore = $score; $selectedRam = $ram; $selectedRamQty = $qty; }
        }
        if (!$selectedRam) { $selectedRam = $rams[0]; $selectedRamQty = min($maxRamSlots, max(1, (int)ceil($neededRam / max(1, (int)($selectedRam['capacity_gb'] ?? 1))))); }
    }

    $payload = [];
    if ($selectedCpu) { $payload['cpu_id'] = (int)$selectedCpu['id']; $payload['cpu_qty'] = $selectedCpuQty; }
    if ($selectedRam) { $payload['ram_id'] = (int)$selectedRam['id']; $payload['ram_qty'] = $selectedRamQty; }
    if (!$payload) return null;

    $labelParts = [];
    if ($selectedCpu) $labelParts[] = $selectedCpuQty . '× CPU';
    if ($selectedRam) $labelParts[] = ($selectedRamQty * (int)($selectedRam['capacity_gb'] ?? 0)) . 'GB RAM';
    return [
        'label' => 'اعمال اصلاح CPU/RAM (' . implode('، ', $labelParts) . ')',
        'type' => 'set_cpu_ram',
        'payload' => $payload,
    ];
}

function normalizeAiPayload(string $raw): array
{
    $decoded = decodeAiJson($raw);
    if (!is_array($decoded)) {
        return [
            'reply' => cleanCustomerReply($raw),
            'question' => 'مایلید کدام بخش را دقیق‌تر بررسی کنیم؟',
            'quick_replies' => [
                ['label' => 'بررسی رم', 'message' => 'رم این کانفیگ را بررسی کن'],
                ['label' => 'بررسی ذخیره‌سازی', 'message' => 'ذخیره‌سازی و RAID را بررسی کن'],
            ],
            'actions' => [],
        ];
    }

    $quickReplies = [];
    foreach (($decoded['quick_replies'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $label = sanitizeText($item['label'] ?? '', 60);
        $msg = sanitizeText($item['message'] ?? $label, 220);
        $quickAction = sanitizeAiAction($item['action'] ?? null);
        if ($label !== '' && $msg !== '') {
            $reply = ['label' => $label, 'message' => $msg];
            if ($quickAction) $reply['action'] = $quickAction;
            $quickReplies[] = $reply;
        }
        if (count($quickReplies) >= 3) break;
    }

    $actions = [];
    foreach (($decoded['actions'] ?? []) as $item) {
        $action = sanitizeAiAction($item);
        if ($action) $actions[] = $action;
        if (count($actions) >= 2) break;
    }

    $reply = cleanCustomerReply((string)($decoded['reply'] ?? ''));
    if ($reply === '') $reply = 'اطلاعات فعلی کانفیگ را بررسی کردم. برای راهنمایی دقیق‌تر، لطفاً یکی از گزینه‌های زیر را انتخاب کنید.';
    $question = sanitizeText($decoded['question'] ?? '', 180);
    if ($question === '') $question = 'دوست دارید کدام بخش را تغییر یا بررسی کنیم؟';

    return [
        'reply' => $reply,
        'question' => $question,
        'quick_replies' => $quickReplies,
        'actions' => $actions,
    ];
}

function enrichWithSafeActions(array $payload, array $compactContext, array $dbSignals = [], string $focusText = ''): array
{
    $config = $compactContext['selected_config'] ?? [];
    $target = $compactContext['target'] ?? [];
    $ram = is_array($config['ram'] ?? null) ? $config['ram'] : null;
    $ramQty = (int)($config['ram_qty'] ?? 0);
    $currentTotalRam = $ram ? ((int)($ram['capacity_gb'] ?? 0) * max(1, $ramQty)) : 0;
    $targetRam = max((int)($target['ram_gb'] ?? 0), $currentTotalRam);

    $replyText = $focusText . ' ' . ($payload['reply'] ?? '') . ' ' . ($payload['question'] ?? '');
    $cpuRamAction = buildCpuRamAction($compactContext, $dbSignals, $replyText);
    if ($cpuRamAction) {
        $hasCpuRamAction = false;
        foreach (($payload['actions'] ?? []) as $action) {
            if (in_array($action['type'] ?? '', ['set_cpu', 'set_ram', 'set_cpu_ram'], true)) $hasCpuRamAction = true;
        }
        if (!$hasCpuRamAction) $payload['actions'][] = $cpuRamAction;
        $payload['quick_replies'][] = [
            'label' => 'بله، CPU و RAM را اصلاح کن',
            'message' => 'بله، اول CPU و رم را اصلاح کن',
            'action' => $cpuRamAction,
        ];
    }
    $isRamFocused = stripos($replyText, 'RAM') !== false || strpos($replyText, 'رم') !== false;
    if ($isRamFocused && $ram && $currentTotalRam > 0) {
        $capacity = max(1, (int)($ram['capacity_gb'] ?? 1));
        $suggestTotal = max($targetRam, $currentTotalRam * 2);
        $suggestTotal = min(1024, (int)(ceil($suggestTotal / $capacity) * $capacity));
        $suggestQty = max($ramQty + 1, (int)ceil($suggestTotal / $capacity));

        $hasRamAction = false;
        foreach (($payload['actions'] ?? []) as $action) {
            if (($action['type'] ?? '') === 'set_ram_total' || ($action['type'] ?? '') === 'set_ram_qty') $hasRamAction = true;
        }
        if (!$hasRamAction && $suggestQty > $ramQty) {
            $payload['actions'][] = [
                'label' => 'ارتقای RAM به ' . ($suggestQty * $capacity) . 'GB',
                'type' => 'set_ram_qty',
                'payload' => ['qty' => $suggestQty],
            ];
        }
    }

    if (empty($payload['quick_replies'])) {
        $payload['quick_replies'] = [
            ['label' => 'اقتصادی‌ترش کن', 'message' => 'چطور این کانفیگ را اقتصادی‌تر کنم؟'],
            ['label' => 'برای رشد آینده', 'message' => 'برای رشد آینده کدام بخش را ارتقا بدهم؟'],
            ['label' => 'بررسی ریسک‌ها', 'message' => 'ریسک‌های اصلی این کانفیگ چیست؟'],
        ];
    }

    $payload['actions'] = array_slice($payload['actions'] ?? [], 0, 2);
    $payload['quick_replies'] = array_slice($payload['quick_replies'] ?? [], 0, 3);
    return $payload;
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
        'خیلی مهم: هیچ جدول Markdown، تحلیل طولانی، thinking، مقدمه اضافه یا داده خام دیتابیس ننویس.',
        'پاسخ مشتری باید کوتاه، خوانا و عملی باشد: حداکثر ۴ بولت کوتاه، هر بولت زیر ۱۸ کلمه.',
        'همیشه با توجه به page.current_step بگو کاربر در کدام مرحله کمک خواسته و فقط همان مرحله را اولویت بده.',
        'حتماً در پایان یک سوال کوتاه از کاربر بپرس.',
        'اگر تغییر قابل اعمال وجود دارد، action امن بده؛ برای اصلاح CPU/RAM از set_cpu_ram استفاده کن. اگر مطمئن نیستی action نده.',
        'فقط بر اساس CONTEXT و DB_SIGNALS پاسخ بده؛ قیمت/موجودی/سازگاری ناموجود را حدس نزن.',
        'هیچ کلید API، مسیر سرور، SQL خام یا اطلاعات محرمانه‌ای را بازگو نکن.',
        'خروجی فقط JSON معتبر باشد؛ بدون ``` و بدون متن بیرون JSON.',
        'Schema دقیق: {"reply":"متن کوتاه با بولت‌های ساده","question":"سوال کوتاه","quick_replies":[{"label":"...","message":"...","action":{"label":"...","type":"set_cpu_ram","payload":{"cpu_id":1,"cpu_qty":2,"ram_id":5,"ram_qty":4}}}],"actions":[{"label":"...","type":"set_cpu|set_ram|set_cpu_ram|set_ram_qty|set_ram_total|set_cpu_qty|set_psu_qty|add_drive_raid10","payload":{"qty":4,"cpu_id":1,"ram_id":5}}]}',
    ]);

    $userQuestion = $message === '__context_init__'
        ? 'کاربر پنجره AI را باز کرده است. با توجه به مرحله فعلی، یک خلاصه کوتاه و سوال بعدی مناسب بده.'
        : $message;

    $messages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        compactHistory($history, $message),
        [[
            'role' => 'user',
            'content' => "USER_QUESTION:\n" . $userQuestion . "\n\nCONTEXT:\n" . json_encode($compactContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\nDB_SIGNALS:\n" . json_encode($dbSignals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]]
    );

    $rawReply = callAiProvider($messages);
    $payload = enrichWithSafeActions(normalizeAiPayload($rawReply), $compactContext, $dbSignals, $userQuestion);

    echo json_encode([
        'status' => 'success',
        'reply' => $payload['reply'],
        'question' => $payload['question'],
        'quick_replies' => $payload['quick_replies'],
        'actions' => $payload['actions'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'ارتباط با سرویس AI برقرار نشد یا تنظیمات آن کامل نیست.',
    ], JSON_UNESCAPED_UNICODE);
}
