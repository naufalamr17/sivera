<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRuleRequest extends FormRequest
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
            'trigger' => ['required', Rule::in(['new_critical', 'becomes_critical', 'reopened', 'high_frequency'])],
            'channel' => ['required', Rule::in(['email', 'qontak'])],
            'target' => $this->input('channel') === 'email'
                ? ['required', 'email', 'max:255']
                : ['required', 'regex:/^[1-9][0-9]{7,14}$/'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(['INFO', 'WARNING', 'ERROR', 'CRITICAL'])],
            'frequency_threshold' => ['required', 'integer', 'between:1,100000'],
            'frequency_window_minutes' => ['required', 'integer', 'between:1,1440'],
            'cooldown_minutes' => ['required', 'integer', 'between:1,1440'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('channel') !== 'qontak') {
            return;
        }

        $number = preg_replace('/\D+/', '', (string) $this->input('target'));

        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        } elseif (str_starts_with($number, '8')) {
            $number = '62'.$number;
        }

        $this->merge(['target' => $number]);
    }
}
