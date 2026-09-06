<?php
// config/secrets.local.example.php
// Copy this file to config/secrets.local.php on the server and fill real secrets.
// config/secrets.local.php is ignored by Git and must never be committed.

return [
    'database' => [
        'host' => 'localhost',
        'database' => 'falnicc1_server_configurator',
        'username' => 'falnicc1_server_configurator',
        'password' => 'CHANGE_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'ai' => [
        'endpoint' => 'http://YOUR_AI_HOST:PORT/v1/chat/completions',
        'api_key' => 'CHANGE_AI_API_KEY',
        'model' => 'Antigravity-Gemini',
        'timeout' => 25,
        'max_tokens' => 900,
        'temperature' => 0.25,
    ],
];
