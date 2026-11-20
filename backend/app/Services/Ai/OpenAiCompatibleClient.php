<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal client for the OpenAI Chat Completions API
 * (POST {baseUrl}/chat/completions), which most providers implement.
 *
 * Messages use the OpenAI shape: [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
 * Usage is normalised to ['input' => ?int, 'output' => ?int, 'total' => ?int].
 */
class OpenAiCompatibleClient
{
    /**
     * Resolves a hostname to its IP addresses. Overridable in tests.
     *
     * @var (\Closure(string): string[])|null
     */
    public static ?\Closure $resolver = null;

    private string $baseUrl;

    /**
     * @param bool $requirePublicHost Refuse to call https URLs that resolve to private or
     *                                reserved addresses. Use for user-supplied base URLs.
     */
    public function __construct(
        string $baseUrl,
        private string $apiKey,
        private int $timeout = 120,
        private bool $requirePublicHost = false,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Send a non-streaming chat completion.
     *
     * @return array{text: string, usage: array{input: ?int, output: ?int, total: ?int}}
     */
    public function chat(string $model, array $messages): array
    {
        $response = $this->send($this->request(), [
            'model' => $model,
            'messages' => $messages,
        ]);

        $json = $response->json();
        if (!is_array($json)) {
            throw new AiProviderException('AI provider returned a response that is not JSON.', $response->status());
        }

        return [
            'text' => ThinkTagFilter::strip($this->contentToString($json['choices'][0]['message']['content'] ?? '')),
            'usage' => $this->normaliseUsage($json['usage'] ?? null),
        ];
    }

    /**
     * Stream a chat completion. Yields ['text' => string, 'usage' => ?array] per event;
     * usage is set on the event(s) that carry it, usually the last one.
     *
     * @return \Generator<int, array{text: string, usage: ?array{input: ?int, output: ?int, total: ?int}}>
     */
    public function stream(string $model, array $messages, bool $includeUsage = true): \Generator
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];
        if ($includeUsage) {
            $payload['stream_options'] = ['include_usage' => true];
        }

        $response = $this->send($this->request()->withOptions(['stream' => true]), $payload);
        $body = $response->toPsrResponse()->getBody();
        $filter = new ThinkTagFilter();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);
                $event = $this->parseLine($line);
                if ($event === false) {
                    break 2; // [DONE]
                }
                if ($event !== null) {
                    yield $this->filterEvent($event, $filter);
                }
            }
        }

        $event = $this->parseLine(rtrim($buffer, "\r\n"));
        if (is_array($event)) {
            yield $this->filterEvent($event, $filter);
        }

        $rest = $filter->flush();
        if ($rest !== '') {
            yield ['text' => $rest, 'usage' => null];
        }
    }

    /**
     * List chat-capable model IDs from GET {baseUrl}/models, sorted.
     *
     * Providers return every model the key can access, including embedding, speech
     * and image models; those are filtered out using capability fields where the
     * provider sends them (Mistral, OpenRouter) and name patterns otherwise.
     *
     * @return string[]
     */
    public function listModels(): array
    {
        $response = $this->send($this->request(), null, 'get', '/models');
        $json = $response->json();
        $models = is_array($json) ? ($json['data'] ?? $json['models'] ?? null) : null;

        if (!is_array($models)) {
            throw new AiProviderException('AI provider returned an unexpected model list.', $response->status());
        }

        return collect($models)
            ->filter(fn ($m) => is_array($m) && is_string($m['id'] ?? null) && self::isChatModel($m))
            // Gemini's OpenAI endpoint returns "models/<name>"; chat requests use the bare name.
            ->map(fn (array $m) => str_starts_with($m['id'], 'models/') ? substr($m['id'], 7) : $m['id'])
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private static function isChatModel(array $model): bool
    {
        // Mistral
        if (isset($model['capabilities']['completion_chat'])) {
            return (bool) $model['capabilities']['completion_chat'];
        }
        // OpenRouter
        if (isset($model['architecture']['output_modalities']) && is_array($model['architecture']['output_modalities'])) {
            return in_array('text', $model['architecture']['output_modalities'], true);
        }

        return !preg_match(
            '/embed|whisper|tts|dall-e|moderation|transcri|realtime|davinci|babbage|imagen|image|veo|aqa|guard|search-preview|audio/i',
            $model['id']
        );
    }

    /**
     * Throws if the URL is not https or its host resolves to a private/reserved address.
     */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new \InvalidArgumentException('The AI base URL must be a valid https:// URL.');
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if (empty($ips)) {
            throw new \InvalidArgumentException("The AI base URL host \"{$host}\" could not be resolved.");
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('The AI base URL must point to a public host.');
            }
        }
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host);
        }

        $ips = gethostbynamel($host) ?: [];
        $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
        foreach ($aaaa as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->withOptions(['allow_redirects' => false]);

        // Some model lists (e.g. OpenRouter) are public, so the key is optional.
        return $this->apiKey !== '' ? $request->withToken($this->apiKey) : $request;
    }

    private function send(PendingRequest $request, ?array $payload, string $method = 'post', string $path = '/chat/completions'): Response
    {
        if ($this->requirePublicHost) {
            try {
                self::assertPublicUrl($this->baseUrl);
            } catch (\InvalidArgumentException $e) {
                throw new AiProviderException($e->getMessage(), null, $e);
            }
        }

        try {
            $response = $method === 'get'
                ? $request->get($this->baseUrl . $path)
                : $request->post($this->baseUrl . $path, $payload);
        } catch (ConnectionException $e) {
            throw new AiProviderException('Could not reach the AI provider: ' . $e->getMessage(), null, $e);
        }

        if ($response->failed() || $response->redirect()) {
            throw new AiProviderException(
                "AI provider returned HTTP {$response->status()}: " . $this->errorMessage($response),
                $response->status()
            );
        }

        return $response;
    }

    /**
     * @return array|null|false Decoded event, null to skip the line, false at [DONE].
     */
    private function parseLine(string $line): array|null|false
    {
        // Blank lines separate events; lines starting with ":" are SSE comments (keep-alives).
        if ($line === '' || str_starts_with($line, ':') || !str_starts_with($line, 'data:')) {
            return null;
        }

        $data = trim(substr($line, 5));
        if ($data === '[DONE]') {
            return false;
        }

        $json = json_decode($data, true);
        if (!is_array($json)) {
            return null;
        }

        if (isset($json['error'])) {
            $error = $json['error'];
            throw new AiProviderException('AI provider error: ' . (is_array($error) ? ($error['message'] ?? json_encode($error)) : (string) $error));
        }

        return $json;
    }

    private function filterEvent(array $event, ThinkTagFilter $filter): array
    {
        // Reasoning fields (delta.reasoning_content / delta.reasoning) are ignored on purpose.
        $content = $this->contentToString($event['choices'][0]['delta']['content'] ?? '');

        return [
            'text' => $content === '' ? '' : $filter->push($content),
            'usage' => isset($event['usage']) ? $this->normaliseUsage($event['usage']) : null,
        ];
    }

    private function contentToString(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (is_array($content)) {
            // Some providers return content as a list of parts.
            return collect($content)
                ->map(fn ($part) => is_array($part) && ($part['type'] ?? 'text') === 'text' ? ($part['text'] ?? '') : '')
                ->implode('');
        }
        return '';
    }

    /** @return array{input: ?int, output: ?int, total: ?int} */
    private function normaliseUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $int = fn ($v) => is_numeric($v) ? (int) $v : null;

        return [
            'input' => $int($usage['prompt_tokens'] ?? null),
            'output' => $int($usage['completion_tokens'] ?? null),
            'total' => $int($usage['total_tokens'] ?? null),
        ];
    }

    private function errorMessage(Response $response): string
    {
        $json = json_decode($response->body(), true);
        // Gemini's OpenAI endpoint wraps errors in a list: [{"error": {...}}]
        if (is_array($json) && array_is_list($json) && isset($json[0]['error'])) {
            $json = $json[0];
        }
        $message = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? null) : null;

        return mb_strimwidth($message ?? trim($response->body()) ?: 'no response body', 0, 500, '…');
    }
}
