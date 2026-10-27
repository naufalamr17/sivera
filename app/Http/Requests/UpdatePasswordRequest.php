<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required', 'string', 'min:12', 'max:255', 'confirmed', 'different:current_password',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
                        $fail('The new password must not exceed 72 bytes.');
                    }
                },
            ],
        ];
    }
}
