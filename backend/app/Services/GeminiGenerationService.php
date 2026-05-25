<?php

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\AiGenerationLog;
use Gemini\Client;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class GeminiGenerationService
{
    private string $model = 'gemma-4-26b-a4b-it';
    private ?User $currentUser = null;
    private ?Project $currentProject = null;
    private ?Client $customClient = null;
    private bool $effectiveFreeTier = false;

    /**
     * Configure the service for a specific user and project context.
     */
    public function forUser(?User $user, ?Project $project = null): self
    {
        $this->currentUser = $user;
        $this->currentProject = $project;
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
        $this->streamGeneration($systemPrompt, $prompt, $onChunk, 'brd');
    }

    /**
     * Stream User Stories generation from an approved BRD.
     */
    public function streamStories(string $systemPrompt, string $approvedBrd, array $additionalContexts, callable $onChunk): void
    {
        $prompt = $this->buildStoriesPrompt($approvedBrd, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating high-quality User Stories. Use the format 'As a [user], I want [goal] so that [benefit]'. Include detailed acceptance criteria for each story.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk, 'stories');
    }

    /**
     * Stream Technical Specification generation from an approved BRD and approved stories.
     */
    public function streamSpec(string $systemPrompt, string $approvedBrd, string $approvedStories, array $additionalContexts, callable $onChunk): void
    {
        $prompt = $this->buildSpecPrompt($approvedBrd, $approvedStories, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating a Technical Specification. Include: System Architecture, Data Models, API Contracts, and Security Considerations.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk, 'spec');
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

        $startTime = microtime(true);

        try {
            $response = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($systemPrompt))
                ->generateContent($prompt);

            $text = $this->safeExtractText($response);
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // Extract usage metadata if available
            $inputTokens = $response->usageMetadata->promptTokenCount ?? null;
            $outputTokens = $response->usageMetadata->candidatesTokenCount ?? null;
            $totalTokens = $response->usageMetadata->totalTokenCount ?? null;

            $this->recordLog(
                type: 'critique',
                status: 'success',
                systemPrompt: $systemPrompt,
                inputPrompt: $prompt,
                outputText: $text,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                totalTokens: $totalTokens,
                executionTimeMs: $duration
            );

            Log::channel('reviews')->info("Critique Generated", [
                'model' => $this->model,
                'user_id' => $this->currentUser?->id,
                'project_id' => $this->currentProject?->id,
            ]);

            return $text;

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            
            $this->recordLog(
                type: 'critique',
                status: 'failure',
                systemPrompt: $systemPrompt,
                inputPrompt: $prompt,
                executionTimeMs: $duration,
                errorMessage: $e->getMessage()
            );

            throw $e;
        }
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

        $this->streamGeneration($systemPrompt, $prompt, $onChunk, 'synthesis');
    }

    /**
     * Generate an architecture summary from codebase context.
     */
    public function generateArchitectureSummary(string $codebaseContext): string
    {
        $systemInstruction = 'You are a Principal Architect. Provide a concise, professional architecture summary in markdown.';
        $prompt = "Analyze the following codebase context and generate a high-level architecture summary. 
        Include: Core tech stack, key components, data flow, and critical architectural patterns.
        
        CODEBASE CONTEXT:
        {$codebaseContext}";

        $startTime = microtime(true);

        try {
            $response = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($systemInstruction))
                ->generateContent($prompt);

            $text = $this->safeExtractText($response);
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->recordLog(
                type: 'architecture',
                status: 'success',
                systemPrompt: $systemInstruction,
                inputPrompt: $prompt,
                outputText: $text,
                inputTokens: $response->usageMetadata->promptTokenCount ?? null,
                outputTokens: $response->usageMetadata->candidatesTokenCount ?? null,
                totalTokens: $response->usageMetadata->totalTokenCount ?? null,
                executionTimeMs: $duration
            );

            return $text;
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordLog(
                type: 'architecture',
                status: 'failure',
                systemPrompt: $systemInstruction,
                inputPrompt: $prompt,
                executionTimeMs: $duration,
                errorMessage: $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * Stream Discovery Chat (The Challenger).
     */
    public function streamDiscoveryChat(string $systemPrompt, string $architectureSummary, array $history, callable $onChunk): void
    {
        // Strip generation-only mandates from the system prompt if they exist
        $cleanSystemPrompt = str_replace(
            "CRITICAL MANDATE: Output ONLY the markdown document. Do NOT include any conversational preamble, internal reasoning, or concluding remarks. Start your response immediately with the first markdown heading (e.g., '# ' or '## ').",
            "",
            $systemPrompt
        );

        $rules = "
        
        EXISTING ARCHITECTURE:
        {$architectureSummary}
        
        INTERVIEW RULES:
        - You are currently in a 'Discovery Interview' mode. Your goal is to grill the user's requirements to find the real need.
        - Ask ONE question at a time. Do not overwhelm the user.
        - Challenge the 'Why' behind every feature. Act as a skeptical but helpful product partner.
        - Protect the integrity of the existing architecture. If a user request introduces tech debt or contradicts the architecture, push back firmly.
        - Once you have a crystal clear understanding of the Problem, Audience, Constraints, and Success Metrics, output exactly [READY_FOR_BRD] and nothing else.
        
        STRICT RESPONSE FORMAT:
        - Output ONLY your response to the user.
        - DO NOT output your internal reasoning, chain of thought, goal analysis, or rule verification.
        - DO NOT restate the user's input or the rules.
        - Be direct, professional, and conversational.
        ";

        $fullSystemPrompt = trim($cleanSystemPrompt) . "\n\n" . trim($rules);

        $historyCollection = collect($history);
        $lastMessage = $historyCollection->pop();
        $lastText = $lastMessage['content'] ?? '';

        $chatHistory = $historyCollection->map(fn($msg) => 
            Content::parse($msg['content'], $msg['role'] === 'assistant' ? Role::MODEL : Role::USER)
        )->toArray();

        $startTime = microtime(true);
        $fullOutput = '';
        $inputTokens = null;
        $outputTokens = null;
        $totalTokens = null;

        try {
            Log::channel('generations')->info("Discovery Chat Started", [
                'model' => $this->model,
                'user_id' => $this->currentUser?->id,
                'project_id' => $this->currentProject?->id,
            ]);

            $chat = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($fullSystemPrompt))
                ->startChat(history: $chatHistory);

            $stream = $chat->streamSendMessage($lastText);

            foreach ($stream as $response) {
                $text = $this->safeExtractText($response);
                if ($text !== '') {
                    $fullOutput .= $text;
                    $onChunk($text);
                }

                if (isset($response->usageMetadata)) {
                    $inputTokens = $response->usageMetadata->promptTokenCount ?? $inputTokens;
                    $outputTokens = $response->usageMetadata->candidatesTokenCount ?? $outputTokens;
                    $totalTokens = $response->usageMetadata->totalTokenCount ?? $totalTokens;
                }
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordLog(
                type: 'discovery',
                status: 'success',
                systemPrompt: $fullSystemPrompt,
                inputPrompt: $lastText,
                outputText: $fullOutput,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                totalTokens: $totalTokens,
                executionTimeMs: $duration
            );

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordLog(
                type: 'discovery',
                status: 'failure',
                systemPrompt: $fullSystemPrompt,
                inputPrompt: $lastText,
                outputText: $fullOutput ?: null,
                executionTimeMs: $duration,
                errorMessage: $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * Run a single streaming generation call, invoking $onChunk for each non-empty text chunk.
     * Includes logic to strip preamble by anchoring to the first markdown heading.
     */
    private function streamGeneration(string $systemPrompt, string $userPrompt, callable $onChunk, string $type = 'document'): void
    {
        if ($this->effectiveFreeTier) {
            sleep(1); // Throttling for free tier start
        }

        $startTime = microtime(true);
        $fullOutput = '';
        $inputTokens = null;
        $outputTokens = null;
        $totalTokens = null;

        try {
            $stream = $this->getClient()->generativeModel(model: $this->model)
                ->withSystemInstruction(Content::parse($systemPrompt))
                ->streamGenerateContent($userPrompt);

            Log::channel('generations')->info("Streaming Generation Started", [
                'model' => $this->model,
                'type' => $type,
                'user_id' => $this->currentUser?->id,
                'project_id' => $this->currentProject?->id,
            ]);

            $buffer = '';
            $headingFound = false;

            foreach ($stream as $response) {
                $text = $this->safeExtractText($response);
                
                if ($text !== '') {
                    $fullOutput .= $text;
                    
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

                // Capture tokens from the final chunk (if available)
                if (isset($response->usageMetadata)) {
                    $inputTokens = $response->usageMetadata->promptTokenCount ?? $inputTokens;
                    $outputTokens = $response->usageMetadata->candidatesTokenCount ?? $outputTokens;
                    $totalTokens = $response->usageMetadata->totalTokenCount ?? $totalTokens;
                }
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordLog(
                type: $type,
                status: 'success',
                systemPrompt: $systemPrompt,
                inputPrompt: $userPrompt,
                outputText: $fullOutput,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                totalTokens: $totalTokens,
                executionTimeMs: $duration
            );

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->recordLog(
                type: $type,
                status: 'failure',
                systemPrompt: $systemPrompt,
                inputPrompt: $userPrompt,
                outputText: $fullOutput ?: null,
                executionTimeMs: $duration,
                errorMessage: $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Safely extract text from a Gemini response, handling multi-part content.
     * Skips parts marked as 'thought' (internal reasoning).
     */
    private function safeExtractText(mixed $response): string
    {
        $text = '';
        foreach ($response->candidates as $candidate) {
            foreach ($candidate->content->parts as $part) {
                // Skip internal reasoning/thoughts
                if (property_exists($part, 'thought') && $part->thought) {
                    continue;
                }
                
                if (isset($part->text)) {
                    $text .= $part->text;
                }
            }
        }
        return $text;
    }

    /**
     * Persist AI generation metrics and metadata.
     */
    private function recordLog(
        string $type,
        string $status,
        ?string $systemPrompt = null,
        ?string $inputPrompt = null,
        ?string $outputText = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?int $totalTokens = null,
        ?int $executionTimeMs = null,
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        try {
            AiGenerationLog::create([
                'user_id' => $this->currentUser?->id,
                'project_id' => $this->currentProject?->id,
                'type' => $type,
                'model' => $this->model,
                'system_prompt' => $systemPrompt,
                'input_prompt' => $inputPrompt,
                'output_text' => $outputText,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'execution_time_ms' => $executionTimeMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to record AI generation log: " . $e->getMessage());
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
