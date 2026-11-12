<?php

namespace App\Exceptions;

use App\Enums\RequestStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidTransitionException extends Exception
{
    public function __construct(
        public readonly RequestStatus $from,
        public readonly RequestStatus $to,
        string $message = ''
    ) {
        $detail = $message !== '' ? $message : "Transition from status '{$from->value}' to '{$to->value}' is not allowed.";
        parent::__construct($detail, 422);
    }

    /**
     * Render the exception into an HTTP JSON response.
     * Keeps response format consistent: status 422 with message and errors shape,
     * while retaining error and from/to keys for debugging/client inspection.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_status_transition',
            'from' => $this->from->value,
            'to' => $this->to->value,
            'errors' => [
                'status' => [$this->getMessage()],
            ],
        ], 422);
    }
}
