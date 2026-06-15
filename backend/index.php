<?php

require_once __DIR__ . '/src/GroqClient.php';

// Suppress PHP error output to prevent information leakage
ini_set('display_errors', '0');
error_reporting(0);

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

// --- CORS ---
$allowedOrigin = getenv('ALLOWED_ORIGIN') ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($allowedOrigin) {
        header("Access-Control-Allow-Origin: $allowedOrigin");
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit(0);
}

// --- Method check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    sendSseError('Method not allowed. Only POST is accepted.', 405);
}

// --- Authentication ---
$expectedToken = getenv('APP_AUTH_TOKEN');
if ($expectedToken) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || !hash_equals($expectedToken, $matches[1])) {
        http_response_code(401);
        sendSseError('Unauthorized', 401);
    }
}

// --- Input validation ---
$rawInput = file_get_contents('php://input');
$maxInputSize = 64 * 1024; // 64 KB
if (strlen($rawInput) > $maxInputSize) {
    sendSseError('Payload too large', 413);
}

if ($rawInput === false || $rawInput === '') {
    sendSseError('Empty request body.', 400);
}

$apiKey = getenv('GROQ_API_KEY');
if ($apiKey === false || $apiKey === '') {
    sendSseError('Server misconfiguration: GROQ_API_KEY is not set.', 500);
}

$client = new GroqClient($apiKey);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendSseError('Invalid JSON: ' . json_last_error_msg(), 400);
}

$messages = $input['messages'] ?? [];
if (!is_array($messages) || empty($messages)) {
    sendSseError('Missing or empty "messages" array in request body.', 400);
}

$validationError = $client->validateMessages($messages);
if ($validationError !== null) {
    sendSseError($validationError, 400);
}

$client->disableOutputBuffering();
$client->sendSseHeaders($allowedOrigin);

try {
    $client->streamChat($messages);
} catch (RuntimeException $e) {
    echo "event: error\ndata: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
    exit(1);
}
