<?php

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\AiGenerationLog;
use App\Services\Ai\OpenAiCompatibleClient;
use Illuminate\Support\Facades\Log;
use App\Services\MemoryGovernorService;

/**
 * Generates requirement documents through any OpenAI-compatible Chat Completions provider.
 *
 * Provider, key and model come from config/ai.php. A user with a personal API key
 * can override the base URL and model in their settings.
 */
class AiGenerationService
{
    private string $model;
    private ?User $currentUser = null;
    private ?Project $currentProject = null;
    private ?\App\Models\Feature $currentFeature = null;
    private ?OpenAiCompatibleClient $client = null;
    private bool $usingPersonalKey = false;
    private bool $effectiveFreeTier = false;
    protected MemoryGovernorService $memory;

    public function __construct(MemoryGovernorService $memory)
    {
        $this->memory = $memory;
        $this->model = (string) config('ai.model');
    }

    /**
     * Configure the service for a specific user and project context.
     *
     * @throws \RuntimeException when neither the user nor the platform has an API key.
     */
    public function forUser(?User $user, ?Project $project = null): self
    {
        $this->currentUser = $user;
        $this->currentProject = $project;
        $this->client = null;
        $timeout = (int) config('ai.request_timeout', 120);

        if ($user && $user->ai_api_key) {
            // Personal key: the user's provider and model override the platform defaults.
            // With no saved base URL the default provider preset (Gemini) is used.
            // User-supplied URLs must be public https hosts.
            $defaultPreset = config('ai.providers.' . config('ai.default_provider'));
            $baseUrl = $user->ai_base_url ?: ($defaultPreset['base_url'] ?? (string) config('ai.base_url'));

            $this->usingPersonalKey = true;
            $this->client = new OpenAiCompatibleClient(
                baseUrl: $baseUrl,
                apiKey: $user->ai_api_key,
                timeout: $timeout,
                requirePublicHost: $user->ai_base_url && !self::isPresetBaseUrl($baseUrl),
            );
            $this->model = $user->ai_model
                ?: (self::presetForBaseUrl($baseUrl)['default_model'] ?? null)
                ?: (string) config('ai.model');
            $this->effectiveFreeTier = (bool) $user->free_tier;
        } else {
            $this->usePlatformCredentials();
        }

        return $this;
    }

    /**
     * @throws \RuntimeException when the platform API key is not configured.
     */
    private function usePlatformCredentials(): void
    {
        if (!config('ai.api_key')) {
            throw new \RuntimeException(
                'No AI API key configured. Please set your personal API key in Settings, or ask your administrator to configure the platform key.'
            );
        }

        $this->usingPersonalKey = false;
        $this->client = new OpenAiCompatibleClient(
            baseUrl: (string) config('ai.base_url'),
            apiKey: (string) config('ai.api_key'),
            timeout: (int) config('ai.request_timeout', 120),
        );
        $this->model = (string) config('ai.model');
        $this->effectiveFreeTier = (bool) config('ai.platform_free_tier', false);
    }

    /**
     * Find the provider preset whose base URL matches, ignoring a trailing slash.
     */
    private static function presetForBaseUrl(string $baseUrl): ?array
    {
        $normalised = rtrim(trim($baseUrl), '/');

        return collect(config('ai.providers', []))
            ->first(fn (array $preset) => rtrim($preset['base_url'] ?? '', '/') === $normalised);
    }

    /**
     * Whether the URL is one of the configured provider presets (trusted, no host check needed).
     */
    public static function isPresetBaseUrl(string $baseUrl): bool
    {
        return self::presetForBaseUrl($baseUrl) !== null;
    }

    /**
     * Whether requests use the current user's personal API key.
     */
    public function usesPersonalKey(): bool
    {
        return $this->usingPersonalKey;
    }

    public function forFeature(\App\Models\Feature $feature): self
    {
        $this->currentFeature = $feature;
        $this->currentProject = $feature->project;
        return $this;
    }

    /**
     * Get the configured client, defaulting to platform credentials when forUser() was not called.
     */
    private function getClient(): OpenAiCompatibleClient
    {
        if (!$this->client) {
            $this->usePlatformCredentials();
        }

        return $this->client;
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
     * Get the model identifier used for generation.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Stream BRD generation from intake fields and/or chat history.
     */
    public function streamBrd(string $systemPrompt, array $intakeFields, array $chatHistory, array $additionalContexts, callable $onChunk, ?\App\Models\RequirementDraft $draft = null): void
    {
        $prompt = $this->buildBrdPrompt($intakeFields, $chatHistory, $additionalContexts);
        $this->streamGeneration($systemPrompt, $prompt, $onChunk, 'brd', $draft);
    }

    /**
     * Stream User Stories generation from an approved BRD.
     */
    public function streamStories(string $systemPrompt, string $approvedBrd, array $additionalContexts, callable $onChunk, ?\App\Models\RequirementDraft $draft = null): void
    {
        $prompt = $this->buildStoriesPrompt($approvedBrd, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating high-quality User Stories. Use the format 'As a [user], I want [goal] so that [benefit]'. Include detailed acceptance criteria for each story.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk, 'stories', $draft);
    }

    /**
     * Stream Technical Specification generation from an approved BRD and approved stories.
     */
    public function streamSpec(string $systemPrompt, string $approvedBrd, string $approvedStories, array $additionalContexts, callable $onChunk, ?\App\Models\RequirementDraft $draft = null): void
    {
        $prompt = $this->buildSpecPrompt($approvedBrd, $approvedStories, $additionalContexts);
        $fullSystemPrompt = $systemPrompt . "\n\nTASK: Now, focus specifically on generating a Technical Specification. Include: System Architecture, Data Models, API Contracts, and Security Considerations.";
        $this->streamGeneration($fullSystemPrompt, $prompt, $onChunk, 'spec', $draft);
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
            $result = $this->getClient()->chat($this->model, [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $text = $result['text'];
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->recordLog(
                type: 'critique',
                status: 'success',
                systemPrompt: $systemPrompt,
                inputPrompt: $prompt,
                outputText: $text,
                inputTokens: $result['usage']['input'],
                outputTokens: $result['usage']['output'],
                totalTokens: $result['usage']['total'],
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
    public function streamSynthesis(string $systemPrompt, string $originalDraft, array $critiques, callable $onChunk, string $draftType = 'document', ?\App\Models\RequirementDraft $draft = null): void
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

        $this->streamGeneration($systemPrompt, $prompt, $onChunk, 'synthesis', $draft);
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
            $result = $this->getClient()->chat($this->model, [
                ['role' => 'system', 'content' => $systemInstruction],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $text = $result['text'];
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->recordLog(
                type: 'architecture',
                status: 'success',
                systemPrompt: $systemInstruction,
                inputPrompt: $prompt,
                outputText: $text,
                inputTokens: $result['usage']['input'],
                outputTokens: $result['usage']['output'],
                totalTokens: $result['usage']['total'],
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
        $hierarchy = [];
        
        // 1. Company Context
        $companyCtx = $this->getCompanyContext();
        if ($companyCtx) {
            $hierarchy[] = "### COMPANY CONTEXT\n" . $companyCtx;
        }

        // 2. Module Context
        $moduleCtx = $this->getModuleContexts();
        if ($moduleCtx) {
            $hierarchy[] = "### MODULE CONTEXT\n" . $moduleCtx;
        }

        // 3. Project Memory (RAG)
        if ($this->currentProject) {
            $lastUserMsg = collect($history)->where('role', 'user')->last()['content'] ?? '';
            $ragModules = $this->memory->getRelevantContext($this->currentProject, $lastUserMsg);
            $ragContext = $this->memory->formatContextForPrompt($ragModules);
            if ($ragContext) {
                $hierarchy[] = "### PROJECT MEMORY\n" . $ragContext;
            }
        }

        $hierarchyStr = !empty($hierarchy) ? implode("\n\n---\n\n", $hierarchy) . "\n\n---\n\n" : "";

        $rules = "
        
        EXISTING ARCHITECTURE:
        {$architectureSummary}
        
        {$hierarchyStr}
        
        INTERVIEW RULES:
        - You are currently in a 'Discovery Interview' mode. Your goal is to grill the user's requirements to find the real need.
        - Ask ONE question at a time. Do not overwhelm the user.
        - Challenge the 'Why' behind every feature. Act as a skeptical but helpful product partner.
        - Protect the integrity of the existing architecture. If a user request introduces tech debt or contradicts the architecture, push back firmly.
        - Once you have a crystal clear understanding of the Problem, Audience, Constraints, and Success Metrics, provide a warm, professional wrap-up message to the user. Summarize what you've learned and inform them that you are ready to generate the requirements.
        - THEN, at the very end of that final message, output exactly [READY_FOR_BRD] to signal completion.
        
        STRICT RESPONSE FORMAT:
        - Output ONLY your response to the user.
        - DO NOT output your internal reasoning, chain of thought, goal analysis, or rule verification.
        - DO NOT restate the user's input or the rules.
        - Be direct, professional, and conversational.
        ";

        $fullSystemPrompt = trim($systemPrompt) . "\n\n" . trim($rules);

        $historyCollection = collect($history);
        $lastMessage = $historyCollection->pop();
        $lastText = $lastMessage['content'] ?? '';

        $messages = [['role' => 'system', 'content' => $fullSystemPrompt]];
        foreach ($historyCollection as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $msg['content'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $lastText];

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

            $stream = $this->getClient()->stream($this->model, $messages, (bool) config('ai.stream_usage', true));

            foreach ($stream as $event) {
                $text = $event['text'];
                if ($text !== '') {
                    $fullOutput .= $text;
                    $onChunk($text);
                }

                if ($event['usage']) {
                    $inputTokens = $event['usage']['input'] ?? $inputTokens;
                    $outputTokens = $event['usage']['output'] ?? $outputTokens;
                    $totalTokens = $event['usage']['total'] ?? $totalTokens;
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
    private function streamGeneration(string $systemPrompt, string $userPrompt, callable $onChunk, string $type = 'document', ?\App\Models\RequirementDraft $draft = null): void
    {
        $hierarchy = [];

        // 0. FEATURE INTENT (Highest Priority - Targeted Refinement)
        if ($draft && $draft->title) {
            $intent = "### TARGET FEATURE: {$draft->title}\n";
            if ($draft->description) {
                $intent .= "USER INTENT: {$draft->description}";
            }
            $hierarchy[] = $intent;
        }
        
        // 1. Company Context (Universal)
        $companyCtx = $this->getCompanyContext();
        if ($companyCtx) {
            $hierarchy[] = "### COMPANY CONTEXT (Universal Mandates)\n" . $companyCtx;
        }

        // 2. Module Context (Project Attached)
        $moduleCtx = $this->getModuleContexts();
        if ($moduleCtx) {
            $hierarchy[] = "### MODULE CONTEXT (Project-Specific Guides)\n" . $moduleCtx;
        }

        // 3. Project Memory (RAG)
        if ($this->currentProject) {
            $ragModules = $this->memory->getRelevantContext($this->currentProject, $userPrompt);
            $ragContext = $this->memory->formatContextForPrompt($ragModules);
            if ($ragContext) {
                $hierarchy[] = "### PROJECT MEMORY (Related Historical Data)\n" . $ragContext;
            }
        }

        if (!empty($hierarchy)) {
            $systemPrompt = implode("\n\n---\n\n", $hierarchy) . "\n\n---\n\n" . $systemPrompt;
        }

        if ($this->effectiveFreeTier) {
            sleep(1); // Throttling for free tier start
        }

        $startTime = microtime(true);
        $fullOutput = '';
        $inputTokens = null;
        $outputTokens = null;
        $totalTokens = null;

        try {
            $stream = $this->getClient()->stream($this->model, [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], (bool) config('ai.stream_usage', true));

            Log::channel('generations')->info("Streaming Generation Started", [
                'model' => $this->model,
                'type' => $type,
                'user_id' => $this->currentUser?->id,
                'project_id' => $this->currentProject?->id,
                'draft_id' => $draft?->id,
            ]);

            $buffer = '';
            $headingFound = false;

            foreach ($stream as $event) {
                $text = $event['text'];

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
                if ($event['usage']) {
                    $inputTokens = $event['usage']['input'] ?? $inputTokens;
                    $outputTokens = $event['usage']['output'] ?? $outputTokens;
                    $totalTokens = $event['usage']['total'] ?? $totalTokens;
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
                executionTimeMs: $duration,
                metadata: ['draft_id' => $draft?->id]
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
                errorMessage: $e->getMessage(),
                metadata: ['draft_id' => $draft?->id]
            );

            throw $e;
        }
    }

    /**
     * Get all company-wide global contexts formatted for prompts.
     */
    private function getCompanyContext(): string
    {
        $contexts = \App\Models\ProjectContext::where('is_global', true)->get();
        if ($contexts->isEmpty()) {
            return "";
        }
        
        return $contexts->map(fn($ctx) => "### Company Context: {$ctx->name}\n{$ctx->content}")
            ->implode("\n\n");
    }

    /**
     * Get all project-attached module contexts formatted for prompts.
     */
    private function getModuleContexts(): string
    {
        if (!$this->currentProject) {
            return "";
        }

        $contexts = $this->currentProject->projectContexts()->get();
        if ($contexts->isEmpty()) {
            return "";
        }

        return $contexts->map(fn($ctx) => "### Module: {$ctx->name}\n{$ctx->content}")
            ->implode("\n\n");
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
                'feature_id' => $this->currentFeature?->id,
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
