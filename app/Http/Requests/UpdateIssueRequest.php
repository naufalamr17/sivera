<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...match ($this->route()?->getName()) {
                'issues.status' => ['status' => ['required', 'in:ACKNOWLEDGED,INVESTIGATING,RESOLVED,IGNORED,REOPENED']],
                'issues.severity' => ['severity' => ['required', 'in:INFO,WARNING,ERROR,CRITICAL']],
                'issues.comments' => ['comment' => ['required', 'string', 'max:5000']],
                default => [],
            },
        ];
    }
}
