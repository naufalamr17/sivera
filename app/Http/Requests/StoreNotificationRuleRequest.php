<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target' => ['required', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'channel' => ['sometimes', Rule::in(['email'])],
            'trigger' => ['sometimes', Rule::in(['daily_digest'])],
        ];
    }
}
