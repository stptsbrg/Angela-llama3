<?php

// ⚠️ DÉSACTIVER TOUT BUFFERING
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

// HEADERS SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? [];

$apiKey = getenv('GROQ_API_KEY');
$url = "https://api.groq.com/openai/v1/chat/completions";

$payload = [
    "model" => "llama3-8b-8192",
    "stream" => true,
    "messages" => $messages
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_WRITEFUNCTION => function ($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    }
]);

curl_exec($ch);
curl_close($ch);
