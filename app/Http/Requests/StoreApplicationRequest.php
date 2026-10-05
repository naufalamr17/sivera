<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:100', Rule::unique('applications', 'slug')->ignore($this->route('application')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'environment' => ['required', 'string', 'max:32'],
            'rate_limit_per_minute' => ['required', 'integer', 'between:1,120'],
            'allowed_ips' => ['array'],
            'allowed_ips.*' => ['ip'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allowed_ips' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('allowed_ips_text', ''))))),
        ]);
    }
}
