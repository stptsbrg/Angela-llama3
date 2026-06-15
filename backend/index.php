<?php

// ⚠️ DÉSACTIVER TOUT BUFFERING
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendSseError('Method not allowed. Only POST is accepted.', 405);
}

// HEADERS SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Validate API key
$apiKey = getenv('GROQ_API_KEY');
if ($apiKey === false || $apiKey === '') {
    sendSseError('Server misconfiguration: GROQ_API_KEY is not set.', 500);
}

// Parse and validate input
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

$url = "https://api.groq.com/openai/v1/chat/completions";

$payload = [
    "model" => "llama3-8b-8192",
    "stream" => true,
    "messages" => $messages
];

$ch = curl_init($url);
if ($ch === false) {
    sendSseError('Failed to initialize HTTP client (curl).', 500);
}

$httpStatusCode = 0;

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$httpStatusCode) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $httpStatusCode = (int)$matches[1];
        }
        return strlen($header);
    },
    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$httpStatusCode) {
        if ($httpStatusCode >= 400) {
            $decoded = json_decode($data, true);
            $errorMsg = $decoded['error']['message']
                ?? $decoded['error']
                ?? "Upstream API error (HTTP $httpStatusCode)";
            echo "event: error\ndata: " . json_encode(['error' => $errorMsg]) . "\n\n";
            flush();
            return -1; // abort transfer
        }
        echo $data;
        flush();
        return strlen($data);
    }
]);

$result = curl_exec($ch);

if ($result === false) {
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    $message = match ($errno) {
        CURLE_OPERATION_TIMEDOUT => 'Request to upstream API timed out.',
        CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve upstream API host.',
        CURLE_COULDNT_CONNECT => 'Could not connect to upstream API.',
        CURLE_SSL_CONNECT_ERROR => 'SSL connection error with upstream API.',
        default => "Upstream request failed: $error (code $errno)",
    };

    echo "event: error\ndata: " . json_encode(['error' => $message]) . "\n\n";
    flush();
    exit(1);
}

curl_close($ch);
