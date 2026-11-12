<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestWorkflow
{
    /**
     * Define the state machine graph of valid transitions.
     * Any status not listed or mapped to an empty array (like rejected and completed) is terminal.
     *
     * @var array<string, list<RequestStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        RequestStatus::Draft->value => [
            RequestStatus::Submitted,
        ],
        RequestStatus::Submitted->value => [
            RequestStatus::Approved,
            RequestStatus::Rejected,
            RequestStatus::Returned,
        ],
        RequestStatus::Returned->value => [
            RequestStatus::Submitted,
        ],
        RequestStatus::Approved->value => [
            RequestStatus::Assigned,
        ],
        RequestStatus::Assigned->value => [
            RequestStatus::InProgress,
        ],
        RequestStatus::InProgress->value => [
            RequestStatus::Completed,
        ],
        RequestStatus::Rejected->value => [],
        RequestStatus::Completed->value => [],
    ];

    /**
     * Check if a transition from current status to the desired status is allowed.
     */
    public function canTransition(ServiceRequest $request, RequestStatus $to): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$request->status->value] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Transition a service request to a new status.
     *
     * @throws InvalidTransitionException
     */
    public function transition(
        ServiceRequest $request,
        RequestStatus $to,
        User $actor,
        ?string $comment = null
    ): ServiceRequest {
        if (! $this->canTransition($request, $to)) {
            throw new InvalidTransitionException($request->status, $to);
        }

        return DB::transaction(function () use ($request, $to, $actor, $comment) {
            $from = $request->status;

            // 1. Update the status on the model
            $request->status = $to;
            $request->save();

            // 2. Write an activity record for audit tracking
            $request->activities()->create([
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $comment,
            ]);

            // 3. Dispatch notification hook
            // TODO: Dispatch notification to stakeholders (e.g. requester, assignee, manager) when notifications phase is implemented.

            return $request->fresh(['activities']);
        });
    }
}
