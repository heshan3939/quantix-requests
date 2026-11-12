<?php

namespace App\Http\Requests\ServiceRequests;

use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approved,rejected,returned'],
            'comment' => [
                Rule::requiredIf(fn () => in_array($this->input('decision'), ['rejected', 'returned'], true)),
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Helper to get target status enum from decision string.
     */
    public function toStatus(): RequestStatus
    {
        return match ((string) $this->validated('decision')) {
            'approved' => RequestStatus::Approved,
            'rejected' => RequestStatus::Rejected,
            'returned' => RequestStatus::Returned,
            default => RequestStatus::Rejected,
        };
    }
}
