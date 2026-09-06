<?php
// config/ai.php
// Central AI provider configuration. Never commit real API keys.

declare(strict_types=1);

if (file_exists(__DIR__ . '/ai.local.php')) {
    /**
     * Optional local override file. This file is intentionally ignored by Git.
     * It may return an array with endpoint, api_key, model, timeout, max_tokens, temperature.
     */
    $localAiConfig = require __DIR__ . '/ai.local.php';
    if (is_array($localAiConfig)) {
        $GLOBALS['LOCAL_AI_CONFIG'] = $localAiConfig;
    }
}

if (!function_exists('envValue')) {
    function envValue(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) $value = $_ENV[$key];
        if ($value === false && isset($_SERVER[$key])) $value = $_SERVER[$key];
        return $value === false || $value === '' ? $default : (string)$value;
    }
}

if (!function_exists('aiConfig')) {
    function aiConfig(): array
    {
        $local = $GLOBALS['LOCAL_AI_CONFIG'] ?? [];

        return [
            'endpoint' => envValue('AI_API_URL', $local['endpoint'] ?? ''),
            'api_key' => envValue('AI_API_KEY', $local['api_key'] ?? ''),
            'model' => envValue('AI_MODEL', $local['model'] ?? 'Antigravity-Gemini'),
            'timeout' => (int)envValue('AI_TIMEOUT', (string)($local['timeout'] ?? 25)),
            'max_tokens' => (int)envValue('AI_MAX_TOKENS', (string)($local['max_tokens'] ?? 900)),
            'temperature' => (float)envValue('AI_TEMPERATURE', (string)($local['temperature'] ?? 0.25)),
        ];
    }
}
