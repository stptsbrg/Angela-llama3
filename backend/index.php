<?php

require_once __DIR__ . '/src/GroqClient.php';

/**
 * Send an SSE error event to the client and terminate.
 */
function sendSseError(string $message, int $httpStatus = 500): void
{
    http_response_code($httpStatus);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');
    echo "event: error\ndata: " . json_encode(['error' => $message]) . "\n\n";
    exit(1);
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendSseError('Method not allowed. Only POST is accepted.', 405);
}

$apiKey = getenv('GROQ_API_KEY');
if ($apiKey === false || $apiKey === '') {
    sendSseError('Server misconfiguration: GROQ_API_KEY is not set.', 500);
}

$client = new GroqClient($apiKey);

$client->disableOutputBuffering();
$client->sendSseHeaders();

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    sendSseError('Empty request body.', 400);
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendSseError('Invalid JSON: ' . json_last_error_msg(), 400);
}

$messages = $input['messages'] ?? [];
if (!is_array($messages) || empty($messages)) {
    sendSseError('Missing or empty "messages" array in request body.', 400);
}

try {
    $client->streamChat($messages);
} catch (RuntimeException $e) {
    echo "event: error\ndata: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
    exit(1);
}
