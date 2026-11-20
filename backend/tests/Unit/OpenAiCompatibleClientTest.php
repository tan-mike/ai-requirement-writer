<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Services\Ai\ThinkTagFilter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiCompatibleClientTest extends TestCase
{
    private function client(): OpenAiCompatibleClient
    {
        return new OpenAiCompatibleClient('https://provider.test/v1/', 'key');
    }

    private function collect(\Generator $stream): array
    {
        $text = '';
        $usage = null;
        foreach ($stream as $event) {
            $text .= $event['text'];
            $usage = $event['usage'] ?? $usage;
        }
        return [$text, $usage];
    }

    public function test_stream_parses_sse_with_comments_crlf_and_reasoning_fields(): void
    {
        $body = ": OPENROUTER PROCESSING\r\n\r\n"
            . 'data: ' . json_encode(['choices' => [['delta' => ['role' => 'assistant', 'content' => '']]]]) . "\r\n\r\n"
            . 'data: ' . json_encode(['choices' => [['delta' => ['reasoning_content' => 'hidden thoughts']]]]) . "\r\n\r\n"
            . 'data: ' . json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]) . "\r\n\r\n"
            . 'data: ' . json_encode(['choices' => [['delta' => ['content' => ' world']]]]) . "\r\n\r\n"
            . 'data: ' . json_encode(['choices' => [], 'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2, 'total_tokens' => 5]]) . "\r\n\r\n"
            . "data: [DONE]\r\n\r\n";
        Http::fake(['*' => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);

        [$text, $usage] = $this->collect($this->client()->stream('m', [['role' => 'user', 'content' => 'hi']]));

        $this->assertSame('Hello world', $text);
        $this->assertSame(['input' => 3, 'output' => 2, 'total' => 5], $usage);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://provider.test/v1/chat/completions');
    }

    public function test_stream_strips_leading_think_block(): void
    {
        $events = ['<thi', 'nk>planning', ' more</th', 'ink>', "\n\n# Title", "\nBody"];
        $body = collect($events)
            ->map(fn ($c) => 'data: ' . json_encode(['choices' => [['delta' => ['content' => $c]]]]) . "\n\n")
            ->implode('') . "data: [DONE]\n\n";
        Http::fake(['*' => Http::response($body)]);

        [$text] = $this->collect($this->client()->stream('m', [], false));

        $this->assertSame("# Title\nBody", $text);
        Http::assertSent(fn (Request $r) => !isset($r['stream_options']));
    }

    public function test_stream_error_event_throws(): void
    {
        Http::fake(['*' => Http::response('data: ' . json_encode(['error' => ['message' => 'overloaded']]) . "\n\n")]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('AI provider error: overloaded');
        $this->collect($this->client()->stream('m', []));
    }

    public function test_http_error_uses_provider_message_including_gemini_list_shape(): void
    {
        Http::fake(['*' => Http::response([['error' => ['code' => 400, 'message' => 'API key not valid']]], 400)]);

        try {
            $this->client()->chat('m', []);
            $this->fail('Expected exception');
        } catch (AiProviderException $e) {
            $this->assertSame('AI provider returned HTTP 400: API key not valid', $e->getMessage());
            $this->assertSame(400, $e->status);
        }
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('HTTP 302');
        $this->client()->chat('m', []);
    }

    public function test_chat_handles_content_parts_and_missing_usage(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => [['type' => 'text', 'text' => 'a'], ['type' => 'text', 'text' => 'b']]]]],
        ])]);

        $result = $this->client()->chat('m', []);

        $this->assertSame('ab', $result['text']);
        $this->assertSame(['input' => null, 'output' => null, 'total' => null], $result['usage']);
    }

    public function test_think_filter_leaves_non_leading_tags_and_plain_text_alone(): void
    {
        $this->assertSame('Use <think> later', ThinkTagFilter::strip('Use <think> later'));
        $this->assertSame('<', ThinkTagFilter::strip('<'));
        $this->assertSame('answer', ThinkTagFilter::strip("  <THINK>x</THINK>\nanswer"));
        $this->assertSame('', ThinkTagFilter::strip('<think>never closed'));
    }

    public function test_assert_public_url(): void
    {
        OpenAiCompatibleClient::$resolver = fn () => ['8.8.8.8'];
        try {
            OpenAiCompatibleClient::assertPublicUrl('https://api.example.com/v1');
            $this->addToAssertionCount(1);

            foreach (['http://api.example.com', 'https://[::1]/v1', 'https://192.168.0.1', 'not a url'] as $bad) {
                try {
                    OpenAiCompatibleClient::assertPublicUrl($bad);
                    $this->fail("Accepted {$bad}");
                } catch (\InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        } finally {
            OpenAiCompatibleClient::$resolver = null;
        }
    }
}
