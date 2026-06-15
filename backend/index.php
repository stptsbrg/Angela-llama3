<?php

require_once __DIR__ . '/src/GroqClient.php';

$apiKey = getenv('GROQ_API_KEY');
$client = new GroqClient($apiKey ?: '');

$client->disableOutputBuffering();
$client->sendSseHeaders();

$rawInput = file_get_contents('php://input');
$messages = $client->parseMessages($rawInput);

$client->streamChat($messages);
