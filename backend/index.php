<?php

require_once __DIR__ . '/src/GroqClient.php';

// Suppress PHP error output to prevent information leakage
ini_set('display_errors', '0');
error_reporting(0);

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
    exit;
}

// --- Method check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// --- Authentication ---
$expectedToken = getenv('APP_AUTH_TOKEN');
if ($expectedToken) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches) || !hash_equals($expectedToken, $matches[1])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// --- Input validation ---
$rawInput = file_get_contents('php://input');
$maxInputSize = 64 * 1024; // 64 KB
if (strlen($rawInput) > $maxInputSize) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}

$apiKey = getenv('GROQ_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

$client = new GroqClient($apiKey);

$messages = $client->parseMessages($rawInput);

$validationError = $client->validateMessages($messages);
if ($validationError !== null) {
    http_response_code(400);
    echo json_encode(['error' => $validationError]);
    exit;
}

$client->disableOutputBuffering();
$client->sendSseHeaders($allowedOrigin);
$client->streamChat($messages);
