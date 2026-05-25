# Design Doc: AI Generation Logging

## Purpose
Track all AI interactions (generations, critiques, discovery chats) to analyze usage patterns, monitor token costs, and improve prompt effectiveness.

## Proposed Schema: `ai_generation_logs`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BigInt (PK) | Unique ID |
| `user_id` | Unsigned BigInt (FK) | User who triggered the generation (nullable) |
| `project_id` | Unsigned BigInt (FK) | Associated project (nullable) |
| `type` | String | Type of interaction: `brd`, `stories`, `spec`, `critique`, `discovery`, `synthesis`, `architecture` |
| `model` | String | The model name used (e.g., `gemini-1.5-pro`) |
| `system_prompt` | LongText | The system instruction provided to the model |
| `input_prompt` | LongText | The user-level prompt or final built prompt sent |
| `output_text` | LongText | The raw text output from the AI |
| `input_tokens` | Integer | Prompt token count from `usageMetadata` |
| `output_tokens` | Integer | Response token count from `usageMetadata` |
| `total_tokens` | Integer | Total token count |
| `execution_time_ms`| Integer | Time taken for the request |
| `status` | String | `success` or `failure` |
| `error_message` | Text | Error details if `status` is `failure` |
| `metadata` | JSON | Additional context (e.g., persona ID, additional contexts used) |
| `created_at` | Timestamp | Timestamp of log creation |

## Implementation Strategy

### 1. Database Migration
Create a new migration for the `ai_generation_logs` table with appropriate indexes on `user_id`, `project_id`, and `type`.

### 2. Eloquent Model
Create `App\Models\AiGenerationLog` with fillable attributes and relationships to `User` and `Project`.

### 3. Service Integration (`GeminiGenerationService`)
Update `GeminiGenerationService` to:
- Capture start time.
- Extract `usageMetadata` from responses.
- Record logs after every generation call (both streaming and non-streaming).
- Handle failures by logging the error.

### 4. Handling Streaming
For streaming responses, token counts are usually only available in the final chunk or via a separate usage metadata part in some APIs. For Gemini, we need to ensure we capture the final usage metadata if provided in the stream.

## Success Criteria
- Every AI generation event results in a new row in `ai_generation_logs`.
- Accurate token counts are captured for all non-streaming calls.
- (TBD) Investigating if streaming calls in `google-gemini-php/laravel` expose token counts. If not, we may log estimated counts or wait for a library update if critical.

## Cost & Effectiveness Analysis Goals
- **Token Efficiency:** Identify which personas or prompt types are consuming the most tokens.
- **Model Comparison:** Compare performance/cost if multiple models are introduced.
- **Prompt Iteration:** Link logs to human-edited versions of drafts to see which prompts require more manual correction.
