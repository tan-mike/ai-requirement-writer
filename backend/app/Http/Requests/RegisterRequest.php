<?php

namespace App\Http\Requests;

use App\Services\DomainValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $email = $this->input('email');
            if ($email && !app(DomainValidationService::class)->isDomainAllowed($email)) {
                $validator->errors()->add('email', 'Registration is not allowed for this email domain.');
            }
        });
    }
}
