<?php

require_once __DIR__ . '/streaming.php';

/**
 * Send a streaming chat completion request to the Groq API.
 *
 * @param array  $config   Configuration array with 'api_key', 'base_url', and 'model'.
 * @param array  $messages Conversation messages in OpenAI-compatible format.
 * @param bool   $stream   Whether to stream the response (default: true).
 */
function groq_chat_completion(array $config, array $messages, bool $stream = true): void
{
    $url = rtrim($config['base_url'], '/') . '/chat/completions';

    $payload = json_encode([
        'model' => $config['model'],
        'stream' => $stream,
        'messages' => $messages,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key'],
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => create_stream_writer(),
    ]);

    curl_exec($ch);
    curl_close($ch);
}
