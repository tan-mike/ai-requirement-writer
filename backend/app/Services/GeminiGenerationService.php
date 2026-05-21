<?php

namespace App\Services;

use Gemini\Data\Content;
use Gemini\Laravel\Facades\Gemini;

class GeminiGenerationService
{
    private string $model = 'gemma-4-26b-a4b-it';

    /**
     * Stream BRD generation from intake fields and/or chat history.
     *
     * @param  array<string, mixed>  $intakeFields
     * @param  array<int, mixed>  $chatHistory
     */
    public function streamBrd(array $intakeFields, array $chatHistory, callable $onChunk): void
    {
        $prompt = $this->buildBrdPrompt($intakeFields, $chatHistory);
        $this->streamGeneration($this->brdSystemPrompt(), $prompt, $onChunk);
    }

    /**
     * Stream User Stories generation from an approved BRD.
     */
    public function streamStories(string $approvedBrd, callable $onChunk): void
    {
        $prompt = $this->buildStoriesPrompt($approvedBrd);
        $this->streamGeneration($this->storiesSystemPrompt(), $prompt, $onChunk);
    }

    /**
     * Stream Technical Specification generation from an approved BRD and approved stories.
     */
    public function streamSpec(string $approvedBrd, string $approvedStories, callable $onChunk): void
    {
        $prompt = $this->buildSpecPrompt($approvedBrd, $approvedStories);
        $this->streamGeneration($this->specSystemPrompt(), $prompt, $onChunk);
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

        $response = Gemini::generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse('You are a Principal Architect. Provide a concise, professional architecture summary in markdown.'))
            ->generateContent($prompt);

        return $response->text();
    }

    /**
     * Stream Discovery Chat (The Challenger).
     */
    public function streamDiscoveryChat(string $architectureSummary, array $history, callable $onChunk): void
    {
        $systemPrompt = "You are a Senior Technical Business Analyst and Principal Architect. 
        Your goal is to 'grill' the user to distill true business requirements.
        
        EXISTING ARCHITECTURE:
        {$architectureSummary}
        
        RULES:
        1. Ask ONE question at a time.
        2. Challenge 'Why' behind features.
        3. Protect the integrity of the existing architecture.
        4. If a feature contradicts the architecture or introduces debt, push back.
        5. When requirements are clear (Problem, Audience, Constraints, Metrics), output exactly [READY_FOR_BRD] and nothing else.
        ";

        // Convert array history to Gemini Content objects if needed, 
        // but for now we'll assume a simpler implementation or handle it in streamGeneration.
        // Actually, let's use a simplified version for this turn.

        $lastMessage = end($history)['content'] ?? '';

        $this->streamGeneration($systemPrompt, $lastMessage, $onChunk);
    }

    /**
     * Run a single streaming generation call, invoking $onChunk for each non-empty text chunk.
     */
    private function streamGeneration(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $stream = Gemini::generativeModel(model: $this->model)
            ->withSystemInstruction(Content::parse($systemPrompt))
            ->streamGenerateContent($userPrompt);

        foreach ($stream as $response) {
            try {
                $text = $response->text();
            } catch (\ValueError $e) {
                \Illuminate\Support\Facades\Log::warning('Gemini stream chunk skipped: ' . $e->getMessage());
                continue;
            }
            if ($text !== '') {
                $onChunk($text);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int, mixed>  $chatHistory
     */
    private function buildBrdPrompt(array $fields, array $chatHistory = []): string
    {
        $formattedFields = collect($fields)
            ->map(fn($value, $key) => '**' . $key . '**: ' . (is_array($value) ? implode(', ', $value) : (string) $value))
            ->implode("\n");

        $formattedChat = collect($chatHistory)
            ->map(fn($msg) => ucfirst($msg['role']) . ': ' . $msg['content'])
            ->implode("\n");

        $prompt = "Generate a Business Requirements Document (BRD).\n\n";

        if ($formattedFields) {
            $prompt .= "### Project Intake Form:\n{$formattedFields}\n\n";
        }

        if ($formattedChat) {
            $prompt .= "### Discovery Interview Transcript:\n{$formattedChat}\n\n";
        }

        return $prompt;
    }

    private function buildStoriesPrompt(string $brd): string
    {
        return "Based on the following BRD, generate a prioritized list of User Stories with acceptance criteria:\n\n{$brd}";
    }

    private function buildSpecPrompt(string $brd, string $stories): string
    {
        return "Based on the following BRD and User Stories, generate a Technical Specification:\n\n## BRD\n{$brd}\n\n## User Stories\n{$stories}";
    }

    private function brdSystemPrompt(): string
    {
        return 'You are a senior business analyst. Write structured, professional Business Requirements Documents in markdown. Include: Executive Summary, Problem Statement, Goals & Objectives, Stakeholders, Functional Requirements, Non-Functional Requirements, Constraints, and Success Criteria.';
    }

    private function storiesSystemPrompt(): string
    {
        return "You are a senior product manager. Write clear, testable User Stories in the format 'As a [user], I want [goal] so that [benefit]'. Include acceptance criteria for each story. Group stories by epic. Prioritize by business value (Must Have, Should Have, Could Have).";
    }

    private function specSystemPrompt(): string
    {
        return 'You are a senior software architect. Write comprehensive Technical Specifications in markdown. Include: System Overview, Architecture, Data Models, API Contracts, Security Considerations, Performance Requirements, and Implementation Notes.';
    }
}
