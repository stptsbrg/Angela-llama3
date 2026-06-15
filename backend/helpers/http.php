<?php

/**
 * Disable all output buffering for real-time streaming.
 */
function disable_output_buffering(): void
{
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    ini_set('implicit_flush', true);
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);
}

/**
 * Send standard SSE (Server-Sent Events) headers with CORS support.
 *
 * @param string $allowOrigin  The allowed origin (default: '*').
 */
function send_sse_headers(string $allowOrigin = '*'): void
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header("Access-Control-Allow-Origin: $allowOrigin");
}

/**
 * Read and decode JSON from the request body.
 *
 * @return array<string, mixed>
 */
function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}
