<?php

require_once __DIR__ . '/helpers/http.php';
require_once __DIR__ . '/helpers/groq_client.php';

$config = require __DIR__ . '/config.php';

// Prepare streaming response
disable_output_buffering();
send_sse_headers();

// Parse request and call Groq API
$input = get_json_input();
$messages = $input['messages'] ?? [];

groq_chat_completion($config['groq'], $messages);
