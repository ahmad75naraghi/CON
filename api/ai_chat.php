<?php
// api/ai_chat.php
header('Content-Type: application/json; charset=utf-8');

$inputJSON = file_get_contents('php://input');
$request = json_decode($inputJSON, true) ?: [];

$message = trim((string)($request['message'] ?? ''));
$context = $request['context'] ?? [];

function safeValue($value, $fallback = 'نامشخص')
{
    return isset($value) && $value !== '' ? $value : $fallback;
}

function summarizeConfig(array $context): string
{
    $target = $context['target'] ?? [];
    $config = $context['currentConfig'] ?? [];

    $parts = [];
    $parts[] = 'نیاز ثبت‌شده: ' . safeValue($target['cores'] ?? null, 0) . ' Core، ' . safeValue($target['ram'] ?? null, 0) . 'GB RAM، ' . round(((int)($target['storage'] ?? 0)) / 1000, 1) . 'TB Storage' . (!empty($target['gpu']) ? '، همراه GPU' : '');

    if (!empty($config['chassis']['model'])) {
        $parts[] = 'شاسی: ' . $config['chassis']['model'] . ' ' . safeValue($config['chassis']['generation'] ?? null, '');
    }
    if (!empty($config['cpu']['model_name'])) {
        $parts[] = 'پردازنده: ' . (int)($config['cpuQty'] ?? 1) . ' عدد ' . $config['cpu']['model_name'];
    }
    if (!empty($config['ram']['model_name'])) {
        $parts[] = 'رم: ' . (int)($config['ramQty'] ?? 1) . ' عدد ' . $config['ram']['model_name'];
    }
    if (!empty($config['gpu']['model_name'])) {
        $parts[] = 'گرافیک: ' . (int)($config['gpuQty'] ?? 1) . ' عدد ' . $config['gpu']['model_name'];
    }
    if (!empty($config['psu']['model_name'])) {
        $parts[] = 'پاور: ' . (int)($config['psuQty'] ?? 2) . ' عدد ' . $config['psu']['model_name'];
    }

    $driveCount = 0;
    foreach (($config['drives'] ?? []) as $drive) {
        $driveCount += (int)($drive['qty'] ?? 0);
    }
    if ($driveCount > 0) $parts[] = 'تعداد دیسک‌ها: ' . $driveCount;

    return implode("\n", $parts);
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'پیام خالی است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$summary = summarizeConfig(is_array($context) ? $context : []);

// این Endpoint فعلاً اتصال خارجی ندارد تا پروژه بدون وابستگی خارجی اجرا شود.
// در آینده می‌توان همین قرارداد ورودی/خروجی را به سرویس AI واقعی متصل کرد.
if ($message === '__context_init__') {
    echo json_encode([
        'status' => 'success',
        'reply' => "خلاصه اطلاعاتی که برای AI ارسال شد:\n" . $summary
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = "اطلاعات کانفیگ فعلی را دریافت کردم.\n\n" . $summary . "\n\n";
$reply .= "بر اساس همین اطلاعات می‌تونم درباره انتخاب CPU، RAM، Storage، RAID، GPU، Riser و Power راهنمایی کنم. اگر می‌خواید تغییری اعمال بشه، دقیق بگید مثلاً: رم بیشتر، ذخیره‌سازی امن‌تر، گزینه اقتصادی‌تر یا آماده‌سازی برای مجازی‌سازی.";

echo json_encode(['status' => 'success', 'reply' => $reply], JSON_UNESCAPED_UNICODE);
