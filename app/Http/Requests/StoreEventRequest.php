<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'application' => ['required', 'string', Rule::in([$this->attributes->get('sivera_application')?->slug])],
            'environment' => ['required', 'string', Rule::in([$this->attributes->get('sivera_application')?->environment])],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'error', 'critical'])],
            'exception' => ['required', 'array:class,message,file,line'],
            'exception.class' => ['required', 'string', 'max:255'],
            'exception.message' => ['required', 'string', 'max:10000'],
            'exception.file' => ['nullable', 'string', 'max:1000'],
            'exception.line' => ['nullable', 'integer', 'min:0'],
            'request' => ['nullable', 'array:method,route,path'],
            'request.method' => ['nullable', 'string', 'max:10'],
            'request.route' => ['nullable', 'string', 'max:255'],
            'request.path' => ['nullable', 'string', 'max:1000'],
            'user' => ['nullable', 'array:id'],
            'user.id' => ['nullable', 'regex:/^[A-Za-z0-9_-]{1,255}$/'],
            'stack_trace' => ['nullable', 'array', 'max:50'],
            'stack_trace.*' => ['array:file,line,function,class'],
            'stack_trace.*.file' => ['nullable', 'string', 'max:1000'],
            'stack_trace.*.line' => ['nullable', 'integer', 'min:0'],
            'stack_trace.*.function' => ['nullable', 'string', 'max:255'],
            'stack_trace.*.class' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['error' => 'Invalid payload'], 422));
    }
}
