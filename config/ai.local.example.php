<?php
// config/ai.local.example.php
// Copy this file to config/ai.local.php and fill the real API key on the server.
// The real ai.local.php file is ignored by Git.

return [
    'endpoint' => 'http://YOUR_AI_HOST:PORT/v1/chat/completions',
    'api_key' => 'CHANGE_ME',
    'model' => 'Antigravity-Gemini',
    'timeout' => 25,
    'max_tokens' => 900,
    'temperature' => 0.25,
];
