<?php
// api/get_data.php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db   = 'falnicc1_server_configurator';
$user = 'falnicc1_server_configurator';
$pass = ']Mq@b8tIGsCvCbB';
$charset = 'utf8mb4';

// فیلدهای JSON که باید برای هر جدول از رشته به آبجکت/آرایه تبدیل بشن
$jsonFieldsMap = [
    'chassis'        => ['storage_rules', 'cooling_rules'],
    'cpus'           => ['cooling_requirements', 'compatible_chassis_ids'],
    'rams'           => ['compatible_cpu_ids', 'compatible_chassis_ids'],
    'gpus'           => ['compatible_chassis_ids'],
    'psus'           => ['input_voltage_support', 'compatible_chassis_ids'],
    'drives'         => ['compatible_chassis_ids'],
    'controllers'    => ['supported_interfaces', 'compatible_chassis_ids'],
    'networks'       => ['compatible_chassis_ids'],
    'risers'         => ['compatible_chassis_ids'],
    'hbas'           => ['compatible_chassis_ids'],
    'optical_drives' => ['compatible_chassis_ids'],
];

function decodeJsonFields(array &$rows, array $fields)
{
    foreach ($rows as &$row) {
        foreach ($fields as $f) {
            if (array_key_exists($f, $row) && $row[$f] !== null && $row[$f] !== '') {
                $row[$f] = json_decode($row[$f]);
            }
        }
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $chassisId = isset($_GET['chassis_id']) ? filter_var($_GET['chassis_id'], FILTER_VALIDATE_INT) : null;

    // ---------------------------------------------------------------
    // حالت ۱: بدون chassis_id → فقط لیست شاسی‌ها (برای پر کردن دراپ‌داون اول)
    // ---------------------------------------------------------------
    if (!$chassisId) {
        $data = ['chassis' => $pdo->query('SELECT * FROM Chassis')->fetchAll()];
        decodeJsonFields($data['chassis'], $jsonFieldsMap['chassis']);
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // ---------------------------------------------------------------
    // حالت ۲: chassis_id مشخص شده → فقط قطعات سازگار با همین شاسی
    // قانون سازگاری: compatible_chassis_ids IS NULL یعنی «با همه سازگار»
    // ---------------------------------------------------------------
    $chassisStmt = $pdo->prepare('SELECT * FROM Chassis WHERE id = :id');
    $chassisStmt->execute([':id' => $chassisId]);
    $chassis = $chassisStmt->fetch();

    if (!$chassis) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'شاسی مورد نظر یافت نشد.']);
        exit;
    }

    // شرط عمومی سازگاری با شاسی، برای هر جدولی که ستون compatible_chassis_ids دارد
    $compatClause = '(compatible_chassis_ids IS NULL OR JSON_CONTAINS(compatible_chassis_ids, :cid))';
    $cidParam = json_encode($chassisId); // مثلا "5" -> رشته JSON معتبر برای یک مقدار عددی

    $data = ['chassis' => [$chassis]];

    // CPUs: هم باید سوکتش با شاسی بخونه، هم سازگاری صریح رعایت بشه
    $stmt = $pdo->prepare("SELECT * FROM CPUs WHERE socket_type = :socket AND $compatClause");
    $stmt->execute([':socket' => $chassis['cpu_socket_type'], ':cid' => $cidParam]);
    $data['cpus'] = $stmt->fetchAll();

    // RAMs: نسل حافظه باید با شاسی بخونه (سازگاری دقیق با CPU انتخابی، سمت کلاینت چک میشه)
    $stmt = $pdo->prepare("SELECT * FROM RAMs WHERE memory_generation = :ramgen AND $compatClause");
    $stmt->execute([':ramgen' => $chassis['ram_generation'], ':cid' => $cidParam]);
    $data['rams'] = $stmt->fetchAll();

    // استخراج نوع هارد مجاز از قوانین شاسی
    $storageRules = json_decode($chassis['storage_rules'], true);
    $baseDriveType = $storageRules['base_drive_type'] ?? null;

    if ($baseDriveType) {
        $stmt = $pdo->prepare("SELECT * FROM `Storage_Drives` WHERE form_factor = :ff AND $compatClause");
        $stmt->execute([':ff' => $baseDriveType, ':cid' => $cidParam]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `Storage_Drives` WHERE $compatClause");
        $stmt->execute([':cid' => $cidParam]);
    }
    $data['drives'] = $stmt->fetchAll();

    // بقیه‌ی جدول‌ها فقط بر اساس compatible_chassis_ids
    $simpleTables = [
        'psus'           => 'Power_Supplies',
        'controllers'    => 'Storage_Controllers',
        'gpus'           => 'GPUs',
        'networks'       => 'Network_Adapters',
        'risers'         => 'Risers',
        'hbas'           => 'HBAs',
        'optical_drives' => 'Optical_Drives',
    ];
    foreach ($simpleTables as $key => $table) {
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE $compatClause");
        $stmt->execute([':cid' => $cidParam]);
        $data[$key] = $stmt->fetchAll();
    }

    // تبدیل فیلدهای JSON به آبجکت/آرایه واقعی برای همه‌ی جدول‌ها
    foreach ($jsonFieldsMap as $key => $fields) {
        if (isset($data[$key])) decodeJsonFields($data[$key], $fields);
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
