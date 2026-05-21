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
            'type' => ['required', 'string'],
            'repository_url' => ['nullable', 'url', 'required_without_all:repository_path,context_file'],
            'repository_path' => ['nullable', 'string', 'required_without_all:repository_url,context_file'],
            'context_file' => ['nullable', 'file', 'max:2048', 'mimes:txt,json,xml,md', 'required_without_all:repository_url,repository_path'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'mode' => ['nullable', 'string'],
        ];
    }
}
