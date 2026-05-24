<?php

namespace App\Services;

use App\Models\User;
use Gemini\Client;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class GeminiGenerationService
{
    private string $model = 'gemma-4-26b-a4b-it';
    private ?User $currentUser = null;
    private ?Client $customClient = null;
    private bool $effectiveFreeTier = false;

    /**
     * Configure the service for a specific user context.
     */
    public function forUser(?User $user): self
    {
        $this->currentUser = $user;
        $this->customClient = null;
        $this->effectiveFreeTier = (bool) config('gemini.platform_free_tier', false);

        if ($user) {
            if ($user->gemini_api_key) {
                // Initialize custom client with user's personal key
                $this->customClient = \Gemini::factory()
                    ->withApiKey($user->gemini_api_key)
                    ->withHttpHeader('Accept', 'application/json')
                    ->make();
                
                // Use user's personal free-tier setting for their own key
                $this->effectiveFreeTier = (bool) $user->free_tier;
            } else {
                // Use platform key with platform free-tier setting
                $this->effectiveFreeTier = (bool) config('gemini.platform_free_tier', false);
            }
        }

        return $this;
    }

    /**
     * Get the active client (custom or platform default).
     */
    private function getClient(): mixed
    {
        return $this->customClient ?? Gemini::getFacadeRoot();
    }

    /**
     * Set the model to be used for generation.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Stream BRD generation from intake fields and/or chat history.
     */
    public function streamBrd(string $systemPrompt, array $intakeFields, array $chatHistory, array $additionalContexts, callable $onChunk): void
    {
        $prompt = $this->buildBrdPrompt($intakeFields, $chatHistory, $additionalContexts);
        $this->streamGeneration($systemPrompt, $prompt, $onChunk);
    }

    /**
     * Stream User Stories generation from an approved BRD.
     */
    public function streamStories(string $systemPrompt, string $approvedBrd, array $additionalContexts, callable $onChunk): void
    {
        $prompt = $this->buildStoriesPrompt($approvedBrd, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating high-quality User Stories. Use the format 'As a [user], I want [goal] so that [benefit]'. Include detailed acceptance criteria for each story.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk);
    }

    /**
     * Stream Technical Specification generation from an approved BRD and approved stories.
     */
    public function streamSpec(string $systemPrompt, string $approvedBrd, string $approvedStories, array $additionalContexts, callable $onChunk): void
    {
        $prompt = $this->buildSpecPrompt($approvedBrd, $approvedStories, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating a Technical Specification. Include: System Architecture, Data Models, API Contracts, and Security Considerations.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk);
    }

    /**
     * Generate a critique from a draft using a reviewer persona.
     */
    public function generateCritique(string $systemPrompt, string $draftContent, string $draftType = 'document'): string
    {
        $typeLabel = match($draftType) {
            'brd' => 'Business Requirements Document (BRD)',
            'stories' => 'User Stories',
            'spec' => 'Technical Specification',
            default => 'document',
        };

        $prompt = "Please review the following {$typeLabel} and provide a detailed, actionable critique based on your expertise.\n\nDOCUMENT CONTENT:\n{$draftContent}";

        if ($this->effectiveFreeTier) {
            sleep(2); // Throttling for free tier
        }

        $response = $this->getClient()->generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse($systemPrompt))
            ->generateContent($prompt);

        $text = $this->safeExtractText($response);

        Log::channel('reviews')->info("Critique Generated", [
            'model' => $this->model,
            'system_prompt' => $systemPrompt,
            'user_id' => $this->currentUser?->id,
            'critique' => $text,
        ]);

        return $text;
    }

    /**
     * Stream a synthesis of the original draft and committee critiques.
     */
    public function streamSynthesis(string $systemPrompt, string $originalDraft, array $critiques, callable $onChunk, string $draftType = 'document'): void
    {
        $typeLabel = match($draftType) {
            'brd' => 'Business Requirements Document (BRD)',
            'stories' => 'User Stories',
            'spec' => 'Technical Specification',
            default => 'document',
        };

        $formattedCritiques = collect($critiques)
            ->map(fn($critique, $role) => "### Feedback from {$role}:\n{$critique}")
            ->implode("\n\n");

        $prompt = "You previously generated a draft for a {$typeLabel}. A committee of experts has reviewed it and provided feedback. 
        Please rewrite the draft, incorporating ALL the feedback below to create a high-quality, final version.
        
        ORIGINAL DRAFT:
        {$originalDraft}
        
        COMMITTEE FEEDBACK:
        {$formattedCritiques}
        
        TASK: Incorporate all feedback and provide the final, polished {$typeLabel} in markdown.
        FINAL VERSION:";

        $this->streamGeneration($systemPrompt, $prompt, $onChunk);
    }

    /**
     * Generate an architecture summary from codebase context.
     */
    public function generateArchitectureSummary(string $codebaseContext): string
    {
        $prompt = "Analyze the following codebase context and generate a high-level architecture summary. 
        Include: Core tech stack, key components, data flow, and critical architectural patterns.
        
        CODEBASE CONTEXT:
        {$codebaseContext}";

        $response = $this->getClient()->generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse('You are a Principal Architect. Provide a concise, professional architecture summary in markdown.'))
            ->generateContent($prompt);

        return $this->safeExtractText($response);
    }

    /**
     * Stream Discovery Chat (The Challenger).
     */
    public function streamDiscoveryChat(string $systemPrompt, string $architectureSummary, array $history, callable $onChunk): void
    {
        $rules = "
        
        EXISTING ARCHITECTURE:
        {$architectureSummary}
        
        INTERVIEW RULES:
        1. Ask ONE question at a time.
        2. Challenge 'Why' behind features.
        3. Protect the integrity of the existing architecture.
        4. If a feature contradicts the architecture or introduces debt, push back.
        5. When requirements are clear (Problem, Audience, Constraints, Metrics), output exactly [READY_FOR_BRD] and nothing else.
        ";

        $fullSystemPrompt = $systemPrompt . $rules;

        $historyCollection = collect($history);
        $lastMessage = $historyCollection->pop();
        $lastText = $lastMessage['content'] ?? '';

        $chatHistory = $historyCollection->map(fn($msg) => 
            Content::parse($msg['content'], $msg['role'] === 'assistant' ? Role::MODEL : Role::USER)
        )->toArray();

        $chat = $this->getClient()->generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse($fullSystemPrompt))
            ->startChat(history: $chatHistory);

        $stream = $chat->streamSendMessage($lastText);

        foreach ($stream as $response) {
            $text = $this->safeExtractText($response);
            if ($text !== '') {
                $onChunk($text);
            }
        }
    }

    /**
     * Run a single streaming generation call, invoking $onChunk for each non-empty text chunk.
     * Includes logic to strip preamble by anchoring to the first markdown heading.
     */
    private function streamGeneration(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        if ($this->effectiveFreeTier) {
            sleep(1); // Throttling for free tier start
        }
        
        $stream = $this->getClient()->generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse($systemPrompt))
            ->streamGenerateContent($userPrompt);

        Log::channel('generations')->info("Streaming Generation Started", [
            'model' => $this->model,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'user_id' => $this->currentUser?->id,
        ]);

        $buffer = '';
        $headingFound = false;

        foreach ($stream as $response) {
            $text = $this->safeExtractText($response);
            
            if ($text !== '') {
                if (!$headingFound) {
                    $buffer .= $text;
                    
                    // Look for the first markdown heading
                    if (preg_match('/^(.*?)((?:^|\n)#+ \s+)/s', $buffer, $matches)) {
                        $headingFound = true;
                        $cleanText = str_replace($matches[1], '', $buffer);
                        $onChunk($cleanText);
                        $buffer = '';
                    } elseif (strlen($buffer) > 500) {
                        // Failsafe: if no heading found in 500 chars, assume no preamble or no headings
                        $headingFound = true;
                        $onChunk($buffer);
                        $buffer = '';
                    }
                } else {
                    $onChunk($text);
                }

                if ($this->effectiveFreeTier) {
                    usleep(100000); // 100ms delay between chunks in free tier
                }
            }
        }
    }

    /**
     * Safely extract text from a Gemini response, handling multi-part content.
     */
    private function safeExtractText(mixed $response): string
    {
        try {
            // Try the quick accessor first
            return $response->text();
        } catch (\ValueError $e) {
            // Fallback to aggregating all parts if it's a multi-part response
            $text = '';
            foreach ($response->candidates as $candidate) {
                foreach ($candidate->content->parts as $part) {
                    if (isset($part->text)) {
                        $text .= $part->text;
                    }
                }
            }
            return $text;
        }
    }

    private function buildBrdPrompt(array $fields, array $chatHistory = [], array $additionalContexts = []): string
    {
        $prompt = "Generate a Business Requirements Document (BRD).\n\n";

        if (!empty($fields)) {
            $formattedFields = collect($fields)
                ->map(fn($value, $key) => '**' . $key . '**: ' . (is_array($value) ? implode(', ', $value) : (string) $value))
                ->implode("\n");
            $prompt .= "### Project Intake Form:\n{$formattedFields}\n\n";
        }

        if (!empty($chatHistory)) {
            $formattedChat = collect($chatHistory)
                ->map(fn($msg) => ucfirst($msg['role']) . ': ' . $msg['content'])
                ->implode("\n");
            $prompt .= "### Discovery Interview Transcript:\n{$formattedChat}\n\n";
        }

        if (!empty($additionalContexts)) {
            $formattedContexts = collect($additionalContexts)
                ->map(fn($ctx) => "#### {$ctx['name']}:\n{$ctx['content']}")
                ->implode("\n\n");
            $prompt .= "### Additional Contexts:\n{$formattedContexts}\n\n";
        }

        return $prompt;
    }

    private function buildStoriesPrompt(string $brd, array $additionalContexts = []): string
    {
        $prompt = "Based on the following BRD, generate a prioritized list of User Stories with acceptance criteria:\n\n{$brd}";
        
        if (!empty($additionalContexts)) {
            $formattedContexts = collect($additionalContexts)
                ->map(fn($ctx) => "#### {$ctx['name']}:\n{$ctx['content']}")
                ->implode("\n\n");
            $prompt .= "\n\n### Additional Contexts:\n{$formattedContexts}";
        }

        return $prompt;
    }

    private function buildSpecPrompt(string $brd, string $stories, array $additionalContexts = []): string
    {
        $prompt = "Based on the following BRD and User Stories, generate a Technical Specification:\n\n## BRD\n{$brd}\n\n## User Stories\n{$stories}";
        
        if (!empty($additionalContexts)) {
            $formattedContexts = collect($additionalContexts)
                ->map(fn($ctx) => "#### {$ctx['name']}:\n{$ctx['content']}")
                ->implode("\n\n");
            $prompt .= "\n\n### Additional Contexts:\n{$formattedContexts}";
        }

        return $prompt;
    }
}
