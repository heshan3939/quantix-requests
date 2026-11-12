<?php

namespace App\Http\Requests\ServiceRequests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'submit_immediately' => ['sometimes', 'boolean'],
        ];
    }
}
