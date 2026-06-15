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
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if ($writeFunction !== null) {
            $options[CURLOPT_WRITEFUNCTION] = $writeFunction;
        }

        return $options;
    }

    /**
     * Return the SSE response headers.
     */
    public function getSseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
        ];
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
    public function sendSseHeaders(): void
    {
        foreach ($this->getSseHeaders() as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Execute the streaming chat completion request.
     *
     * @throws RuntimeException on curl failures or upstream HTTP errors.
     */
    public function streamChat(array $messages): void
    {
        $payload = $this->buildPayload($messages);
        $httpStatusCode = 0;

        $writeFunction = function ($ch, $data) use (&$httpStatusCode) {
            if ($httpStatusCode >= 400) {
                $decoded = json_decode($data, true);
                $errorMsg = $decoded['error']['message']
                    ?? $decoded['error']
                    ?? "Upstream API error (HTTP $httpStatusCode)";
                throw new RuntimeException($errorMsg);
            }
            echo $data;
            flush();
            return strlen($data);
        };

        $options = $this->buildCurlOptions($payload, $writeFunction);

        $options[CURLOPT_HEADERFUNCTION] = function ($ch, $header) use (&$httpStatusCode) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $httpStatusCode = (int)$matches[1];
            }
            return strlen($header);
        };

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize HTTP client (curl).');
        }

        curl_setopt_array($ch, $options);

        try {
            $result = curl_exec($ch);
        } catch (RuntimeException $e) {
            curl_close($ch);
            throw $e;
        }

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

            throw new RuntimeException($message);
        }

        curl_close($ch);
    }
}
