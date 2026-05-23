<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['webapp', 'mobile', 'api', 'data', 'custom'])],
            'repository_url' => ['nullable', 'url'],
            'repository_path' => ['nullable', 'string'],
            'context_file' => ['nullable', 'file', 'max:2048', 'mimes:txt,json,xml,md'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'lead_persona_id' => ['required', 'exists:personas,id'],
            'mode' => ['nullable', 'string', Rule::in(['template', 'conversational'])],
            'context_ids' => ['nullable', 'array'],
            'context_ids.*' => ['exists:project_contexts,id'],
        ];
    }
}
