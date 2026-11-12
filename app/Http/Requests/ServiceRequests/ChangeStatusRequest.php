<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:in_progress,completed'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function toStatus(): RequestStatus
    {
        return RequestStatus::from($this->validated('status'));
    }
}
