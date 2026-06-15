<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/GroqClient.php';

class GroqClientTest extends TestCase
{
    private GroqClient $client;

    protected function setUp(): void
    {
        $this->client = new GroqClient('test-api-key-123');
    }

    // ── Constructor / Getters ───────────────────────────────────────

    public function testConstructorSetsApiKey(): void
    {
        $this->assertSame('test-api-key-123', $this->client->getApiKey());
    }

    public function testConstructorSetsDefaultApiUrl(): void
    {
        $this->assertSame(
            'https://api.groq.com/openai/v1/chat/completions',
            $this->client->getApiUrl()
        );
    }

    public function testConstructorSetsDefaultModel(): void
    {
        $this->assertSame('llama3-8b-8192', $this->client->getModel());
    }

    public function testConstructorAcceptsCustomApiUrl(): void
    {
        $client = new GroqClient('key', 'https://custom.api/v1');
        $this->assertSame('https://custom.api/v1', $client->getApiUrl());
    }

    public function testConstructorAcceptsCustomModel(): void
    {
        $client = new GroqClient('key', 'https://api.groq.com/openai/v1/chat/completions', 'llama3-70b-8192');
        $this->assertSame('llama3-70b-8192', $client->getModel());
    }

    public function testConstructorWithEmptyApiKey(): void
    {
        $client = new GroqClient('');
        $this->assertSame('', $client->getApiKey());
    }

    // ── parseMessages ───────────────────────────────────────────────

    public function testParseMessagesWithValidJson(): void
    {
        $input = json_encode([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $messages = $this->client->parseMessages($input);

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Hello', $messages[0]['content']);
    }

    public function testParseMessagesWithMultipleMessages(): void
    {
        $input = json_encode([
            'messages' => [
                ['role' => 'system', 'content' => 'You are helpful.'],
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello!'],
                ['role' => 'user', 'content' => 'How are you?'],
            ],
        ]);

        $messages = $this->client->parseMessages($input);

        $this->assertCount(4, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[3]['role']);
    }

    public function testParseMessagesWithEmptyMessagesArray(): void
    {
        $input = json_encode(['messages' => []]);
        $messages = $this->client->parseMessages($input);
        $this->assertSame([], $messages);
    }

    public function testParseMessagesWithMissingMessagesKey(): void
    {
        $input = json_encode(['other' => 'data']);
        $messages = $this->client->parseMessages($input);
        $this->assertSame([], $messages);
    }

    public function testParseMessagesWithInvalidJson(): void
    {
        $messages = $this->client->parseMessages('not-valid-json');
        $this->assertSame([], $messages);
    }

    public function testParseMessagesWithEmptyString(): void
    {
        $messages = $this->client->parseMessages('');
        $this->assertSame([], $messages);
    }

    public function testParseMessagesWithNullJsonValue(): void
    {
        $messages = $this->client->parseMessages('null');
        $this->assertSame([], $messages);
    }

    public function testParseMessagesWithJsonScalar(): void
    {
        $messages = $this->client->parseMessages('"just a string"');
        $this->assertSame([], $messages);
    }

    // ── buildPayload ────────────────────────────────────────────────

    public function testBuildPayloadDefaultStream(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hi']];
        $payload = $this->client->buildPayload($messages);

        $this->assertSame('llama3-8b-8192', $payload['model']);
        $this->assertTrue($payload['stream']);
        $this->assertSame($messages, $payload['messages']);
    }

    public function testBuildPayloadStreamDisabled(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hi']];
        $payload = $this->client->buildPayload($messages, false);

        $this->assertFalse($payload['stream']);
    }

    public function testBuildPayloadWithEmptyMessages(): void
    {
        $payload = $this->client->buildPayload([]);

        $this->assertSame([], $payload['messages']);
        $this->assertSame('llama3-8b-8192', $payload['model']);
    }

    public function testBuildPayloadUsesConfiguredModel(): void
    {
        $client = new GroqClient('key', 'https://api.groq.com/openai/v1/chat/completions', 'mixtral-8x7b-32768');
        $payload = $client->buildPayload([['role' => 'user', 'content' => 'test']]);

        $this->assertSame('mixtral-8x7b-32768', $payload['model']);
    }

    public function testBuildPayloadHasExactlyThreeKeys(): void
    {
        $payload = $this->client->buildPayload([]);
        $this->assertCount(3, $payload);
        $this->assertArrayHasKey('model', $payload);
        $this->assertArrayHasKey('stream', $payload);
        $this->assertArrayHasKey('messages', $payload);
    }

    // ── buildCurlOptions ────────────────────────────────────────────

    public function testBuildCurlOptionsContainsRequiredKeys(): void
    {
        $payload = $this->client->buildPayload([]);
        $opts = $this->client->buildCurlOptions($payload);

        $this->assertArrayHasKey(CURLOPT_POST, $opts);
        $this->assertArrayHasKey(CURLOPT_HTTPHEADER, $opts);
        $this->assertArrayHasKey(CURLOPT_POSTFIELDS, $opts);
        $this->assertArrayHasKey(CURLOPT_RETURNTRANSFER, $opts);
    }

    public function testBuildCurlOptionsSetsPostToTrue(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $this->assertTrue($opts[CURLOPT_POST]);
    }

    public function testBuildCurlOptionsSetsReturnTransferToFalse(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $this->assertFalse($opts[CURLOPT_RETURNTRANSFER]);
    }

    public function testBuildCurlOptionsIncludesAuthorizationHeader(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $headers = $opts[CURLOPT_HTTPHEADER];

        $this->assertContains('Authorization: Bearer test-api-key-123', $headers);
    }

    public function testBuildCurlOptionsIncludesContentTypeHeader(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $headers = $opts[CURLOPT_HTTPHEADER];

        $this->assertContains('Content-Type: application/json', $headers);
    }

    public function testBuildCurlOptionsEncodesPayloadAsJson(): void
    {
        $payload = ['model' => 'test', 'stream' => true, 'messages' => []];
        $opts = $this->client->buildCurlOptions($payload);

        $this->assertSame(json_encode($payload), $opts[CURLOPT_POSTFIELDS]);
    }

    public function testBuildCurlOptionsWithoutWriteFunction(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $this->assertArrayNotHasKey(CURLOPT_WRITEFUNCTION, $opts);
    }

    public function testBuildCurlOptionsWithWriteFunction(): void
    {
        $fn = function ($ch, $data) { return strlen($data); };
        $opts = $this->client->buildCurlOptions([], $fn);

        $this->assertArrayHasKey(CURLOPT_WRITEFUNCTION, $opts);
        $this->assertSame($fn, $opts[CURLOPT_WRITEFUNCTION]);
    }

    public function testBuildCurlOptionsHeaderCountIsTwo(): void
    {
        $opts = $this->client->buildCurlOptions([]);
        $this->assertCount(2, $opts[CURLOPT_HTTPHEADER]);
    }

    // ── getSseHeaders ───────────────────────────────────────────────

    public function testGetSseHeadersReturnsCorrectContentType(): void
    {
        $headers = $this->client->getSseHeaders();
        $this->assertSame('text/event-stream', $headers['Content-Type']);
    }

    public function testGetSseHeadersReturnsNoCache(): void
    {
        $headers = $this->client->getSseHeaders();
        $this->assertSame('no-cache', $headers['Cache-Control']);
    }

    public function testGetSseHeadersReturnsKeepAlive(): void
    {
        $headers = $this->client->getSseHeaders();
        $this->assertSame('keep-alive', $headers['Connection']);
    }

    public function testGetSseHeadersReturnsCorsWildcard(): void
    {
        $headers = $this->client->getSseHeaders();
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
    }

    public function testGetSseHeadersContainsFourEntries(): void
    {
        $headers = $this->client->getSseHeaders();
        $this->assertCount(4, $headers);
    }

    // ── Integration-style: round-trip parse → build ─────────────────

    public function testEndToEndParseAndBuildPayload(): void
    {
        $rawInput = json_encode([
            'messages' => [
                ['role' => 'system', 'content' => 'Be concise.'],
                ['role' => 'user', 'content' => 'What is PHP?'],
            ],
        ]);

        $messages = $this->client->parseMessages($rawInput);
        $payload = $this->client->buildPayload($messages);

        $this->assertSame('llama3-8b-8192', $payload['model']);
        $this->assertTrue($payload['stream']);
        $this->assertCount(2, $payload['messages']);
        $this->assertSame('Be concise.', $payload['messages'][0]['content']);
    }

    public function testEndToEndBuildPayloadAndCurlOptions(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $payload = $this->client->buildPayload($messages);
        $opts = $this->client->buildCurlOptions($payload);

        $decoded = json_decode($opts[CURLOPT_POSTFIELDS], true);
        $this->assertSame($messages, $decoded['messages']);
        $this->assertSame('llama3-8b-8192', $decoded['model']);
    }

    // ── streamChat (via subclass override) ──────────────────────────

    public function testStreamChatCallsCurlWithCorrectUrl(): void
    {
        $spy = new GroqClientCurlSpy('test-key', 'https://test.api/v1');
        $spy->streamChat([['role' => 'user', 'content' => 'test']]);

        $this->assertSame('https://test.api/v1', $spy->capturedUrl);
    }

    public function testStreamChatPassesPayloadInCurlOptions(): void
    {
        $spy = new GroqClientCurlSpy('test-key');
        $spy->streamChat([['role' => 'user', 'content' => 'Hi']]);

        $decoded = json_decode($spy->capturedOptions[CURLOPT_POSTFIELDS], true);
        $this->assertSame('Hi', $decoded['messages'][0]['content']);
        $this->assertTrue($decoded['stream']);
    }

    public function testStreamChatSetsWriteFunction(): void
    {
        $spy = new GroqClientCurlSpy('test-key');
        $spy->streamChat([]);

        $this->assertArrayHasKey(CURLOPT_WRITEFUNCTION, $spy->capturedOptions);
        $this->assertIsCallable($spy->capturedOptions[CURLOPT_WRITEFUNCTION]);
    }

    public function testStreamChatWriteFunctionReturnsDataLength(): void
    {
        $spy = new GroqClientCurlSpy('test-key');
        $spy->streamChat([]);

        $writeFn = $spy->capturedOptions[CURLOPT_WRITEFUNCTION];
        ob_start();
        $result = $writeFn(null, 'hello');
        ob_end_clean();

        $this->assertSame(5, $result);
    }

    public function testStreamChatWriteFunctionEchoesData(): void
    {
        $spy = new GroqClientCurlSpy('test-key');
        $spy->streamChat([]);

        $writeFn = $spy->capturedOptions[CURLOPT_WRITEFUNCTION];
        ob_start();
        $writeFn(null, 'streamed-data');
        $output = ob_get_clean();

        $this->assertSame('streamed-data', $output);
    }
}

/**
 * Test spy that captures cURL arguments instead of making real HTTP calls.
 */
class GroqClientCurlSpy extends GroqClient
{
    public ?string $capturedUrl = null;
    public ?array $capturedOptions = null;

    public function streamChat(array $messages): void
    {
        $payload = $this->buildPayload($messages);

        $writeFunction = function ($ch, $data) {
            echo $data;
            flush();
            return strlen($data);
        };

        $this->capturedOptions = $this->buildCurlOptions($payload, $writeFunction);
        $this->capturedUrl = $this->getApiUrl();
    }
}
