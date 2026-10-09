<?php

return [
    // Tetap mati sampai aplikasi dinyatakan siap. Kunci yang terisi tidak ikut terpakai.
    'remote' => (bool) env('ASSISTANT_REMOTE', false),
    'api_key' => env('ASSISTANT_API_KEY'),
    'base_url' => env('ASSISTANT_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('ASSISTANT_MODEL', 'gpt-4o-mini'),
    'hourly_limit' => (int) env('ASSISTANT_HOURLY_LIMIT', 30),
];
