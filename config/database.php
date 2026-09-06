<?php
// config/database.php
// Central database configuration. Never commit real credentials.

declare(strict_types=1);

$sharedSecretsFile = __DIR__ . '/secrets.local.php';
if (file_exists($sharedSecretsFile)) {
    /**
     * Optional shared secrets file for all local credentials.
     * Shape: ['database' => [...], 'ai' => [...]]
     */
    $sharedSecrets = require $sharedSecretsFile;
    if (is_array($sharedSecrets) && isset($sharedSecrets['database']) && is_array($sharedSecrets['database'])) {
        $GLOBALS['LOCAL_DATABASE_CONFIG'] = array_merge($GLOBALS['LOCAL_DATABASE_CONFIG'] ?? [], $sharedSecrets['database']);
    }
}

if (file_exists(__DIR__ . '/database.local.php')) {
    /**
     * Optional local override file. This file is intentionally ignored by Git.
     * It may return an array with host, database, username, password, charset.
     */
    $localDatabaseConfig = require __DIR__ . '/database.local.php';
    if (is_array($localDatabaseConfig)) {
        $GLOBALS['LOCAL_DATABASE_CONFIG'] = $localDatabaseConfig;
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

if (!function_exists('databaseConfig')) {
    function databaseConfig(): array
    {
        $local = $GLOBALS['LOCAL_DATABASE_CONFIG'] ?? [];

        return [
            'host' => envValue('DB_HOST', $local['host'] ?? 'localhost'),
            'database' => envValue('DB_NAME', $local['database'] ?? 'falnicc1_server_configurator'),
            'username' => envValue('DB_USER', $local['username'] ?? 'falnicc1_server_configurator'),
            'password' => envValue('DB_PASS', $local['password'] ?? ''),
            'charset' => envValue('DB_CHARSET', $local['charset'] ?? 'utf8mb4'),
        ];
    }
}

if (!function_exists('databaseConnection')) {
    function databaseConnection(): PDO
    {
        $config = databaseConfig();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
