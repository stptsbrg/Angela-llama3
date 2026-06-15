<?php

class GroqClient
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(
        string $apiKey,
        string $apiUrl = 'https://api.groq.com/openai/v1/chat/completions',
        string $model = 'llama3-8b-8192'
    ) {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        $this->model = $model;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    private const ALLOWED_ROLES = ['system', 'user', 'assistant'];

    /**
     * Parse the raw JSON input body and extract the messages array.
     */
    public function parseMessages(string $rawInput): array
    {
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            return [];
        }
        return $input['messages'] ?? [];
    }

    /**
     * Validate that each message has the expected structure.
     * Returns null on success, or an error string on failure.
     */
    public function validateMessages(array $messages): ?string
    {
        if (empty($messages)) {
            return 'Messages array must not be empty';
        }
        foreach ($messages as $msg) {
            if (!is_array($msg)
                || !isset($msg['role'], $msg['content'])
                || !is_string($msg['role'])
                || !is_string($msg['content'])
                || !in_array($msg['role'], self::ALLOWED_ROLES, true)
            ) {
                return 'Invalid message format';
            }
        }
        return null;
    }

    /**
     * Build the payload for the Groq API request.
     */
    public function buildPayload(array $messages, bool $stream = true): array
    {
        return [
            'model' => $this->model,
            'stream' => $stream,
            'messages' => $messages,
        ];
    }

    /**
     * Build cURL options array for the API request.
     */
    public function buildCurlOptions(array $payload, ?callable $writeFunction = null): array
    {
        $options = [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($writeFunction !== null) {
            $options[CURLOPT_WRITEFUNCTION] = $writeFunction;
        }

        return $options;
    }

    /**
     * Return the SSE response headers.
     */
    public function getSseHeaders(?string $allowedOrigin = null): array
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ];
        if ($allowedOrigin !== null) {
            $headers['Access-Control-Allow-Origin'] = $allowedOrigin;
        }
        return $headers;
    }

    /**
     * Disable output buffering for streaming responses.
     */
    public function disableOutputBuffering(): void
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
     * Send SSE headers.
     */
    public function sendSseHeaders(?string $allowedOrigin = null): void
    {
        foreach ($this->getSseHeaders($allowedOrigin) as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Execute the streaming chat completion request.
     */
    public function streamChat(array $messages): void
    {
        $payload = $this->buildPayload($messages);

        $writeFunction = function ($ch, $data) {
            echo $data;
            flush();
            return strlen($data);
        };

        $options = $this->buildCurlOptions($payload, $writeFunction);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        if ($result === false) {
            error_log('Groq API curl error: ' . curl_error($ch));
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode === 0) {
                http_response_code(502);
            }
        }
        curl_close($ch);
    }
}
