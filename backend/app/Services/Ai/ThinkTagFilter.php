<?php

namespace App\Services\Ai;

/**
 * Removes a leading <think>...</think> reasoning block from model output.
 *
 * Some models served over OpenAI-compatible APIs (DeepSeek R1, Qwen, Ollama
 * models) put their reasoning inline in the content field. Only a block at the
 * very start of the output is removed, so documents that mention the tag later
 * are left alone. Works on streamed chunks: feed each chunk to push() and call
 * flush() at the end.
 */
class ThinkTagFilter
{
    private const OPEN = '<think>';
    private const CLOSE = '</think>';

    private string $phase = 'start'; // start | thinking | after | passthrough
    private string $buffer = '';

    public function push(string $chunk): string
    {
        if ($this->phase === 'passthrough') {
            return $chunk;
        }

        if ($this->phase === 'after') {
            // Drop whitespace between </think> and the answer, even across chunks.
            $chunk = ltrim($chunk);
            if ($chunk === '') {
                return '';
            }
            return $this->passthrough($chunk);
        }

        $this->buffer .= $chunk;

        if ($this->phase === 'start') {
            $trimmed = ltrim($this->buffer);
            if ($trimmed === '') {
                return '';
            }
            if (stripos($trimmed, self::OPEN) === 0) {
                $this->phase = 'thinking';
                $this->buffer = substr($trimmed, strlen(self::OPEN));
            } elseif (strlen($trimmed) < strlen(self::OPEN) && stripos(self::OPEN, $trimmed) === 0) {
                return ''; // could still become "<think>"; wait for more
            } else {
                return $this->passthrough($this->buffer);
            }
        }

        // phase === 'thinking'
        $end = stripos($this->buffer, self::CLOSE);
        if ($end === false) {
            // Keep only enough to detect a closing tag split across chunks.
            $this->buffer = substr($this->buffer, -(strlen(self::CLOSE) - 1));
            return '';
        }

        $rest = substr($this->buffer, $end + strlen(self::CLOSE));
        $this->phase = 'after';
        $this->buffer = '';

        return $this->push($rest);
    }

    public function flush(): string
    {
        // An unclosed reasoning block is dropped.
        $rest = $this->phase === 'start' ? $this->buffer : '';
        $this->buffer = '';
        return $rest;
    }

    public static function strip(string $text): string
    {
        $filter = new self();
        return $filter->push($text) . $filter->flush();
    }

    private function passthrough(string $text): string
    {
        $this->phase = 'passthrough';
        $this->buffer = '';
        return $text;
    }
}
