<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wa_gateway' => [
        'provider' => env('WA_GATEWAY_PROVIDER', 'fonnte'),
        'token'    => env('WA_GATEWAY_TOKEN', ''),
        'sender'   => env('WA_GATEWAY_SENDER', ''),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
    ],

    // FASE 0: backend LLM yang bisa ditukar (OpenAI <-> Ollama/Qwen lokal) lewat .env.
    // Default = OpenAI (perilaku TIDAK berubah sampai LLM_* di-set). base_url = root '/v1';
    // kode menambahkan '/chat/completions'. vision_model dipakai PdfOcrService + OCR kontrak.
    'llm' => [
        'driver'       => env('LLM_DRIVER', 'openai'),
        'base_url'     => env('LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model'        => env('LLM_MODEL', 'gpt-4o-mini'),
        'vision_model' => env('LLM_VISION_MODEL', 'gpt-4o-mini'),
        'api_key'      => env('LLM_API_KEY', env('OPENAI_API_KEY', '')),
        'timeout'      => (int) env('LLM_TIMEOUT', 120),
        // FASE C failover: kalau backend utama (mis. Ollama lokal) tak terjangkau/timeout,
        // otomatis jatuh ke OpenAI. Hanya aktif kalau driver != openai + key OpenAI ada.
        'fallback'       => env('LLM_FALLBACK', true),
        'fallback_url'   => env('LLM_FALLBACK_URL', 'https://api.openai.com/v1/chat/completions'),
        'fallback_model' => env('LLM_FALLBACK_MODEL', 'gpt-4o-mini'),
        'fallback_key'   => env('OPENAI_API_KEY', ''),
    ],

    // HYBRID: backend untuk pekerjaan BATCH (parser KAK/Kontrak/RAB/Penawaran + OCR PDF).
    // Tidak ada user yang nunggu di sini -> aman pakai Qwen lokal (gratis). Chat interaktif
    // tetap pakai 'llm' di atas (OpenAI). Kalau LLM_BATCH_* tak di-set, otomatis ikut 'llm'
    // (perilaku lama tak berubah). timeout lebih panjang krn model lokal lambat.
    'llm_batch' => [
        'driver'       => env('LLM_BATCH_DRIVER', env('LLM_DRIVER', 'openai')),
        'base_url'     => env('LLM_BATCH_BASE_URL', env('LLM_BASE_URL', 'https://api.openai.com/v1')),
        'model'        => env('LLM_BATCH_MODEL', env('LLM_MODEL', 'gpt-4o-mini')),
        'vision_model' => env('LLM_BATCH_VISION_MODEL', env('LLM_VISION_MODEL', 'gpt-4o-mini')),
        'api_key'      => env('LLM_BATCH_API_KEY', env('LLM_API_KEY', env('OPENAI_API_KEY', ''))),
        'timeout'      => (int) env('LLM_BATCH_TIMEOUT', 300),
    ],

    // Python interpreter used by helper scripts (OCR render, laporan renderer/composer).
    // Defaults to `python3`. On prod set PYTHON_BIN to a venv path, e.g.
    // PYTHON_BIN=/root/projects/karta/.venv/bin/python
    'python' => [
        'bin' => env('PYTHON_BIN', 'python3'),
    ],

];
