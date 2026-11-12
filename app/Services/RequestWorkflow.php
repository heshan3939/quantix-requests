<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

            // 3. Dispatch notifications according to the target status
            $this->dispatchNotifications($request, $from, $to, $actor, $comment);

            return $request->fresh(['activities']);
        });
    }

    /**
     * Determine recipients and dispatch queued notifications based on state changes:
     * - submitted -> notify the requester's manager
     * - approved, rejected, returned -> notify the requester (and operations users when approved)
     * - assigned -> notify the assignee
     * - completed -> notify the requester
     */
    private function dispatchNotifications(
        ServiceRequest $request,
        RequestStatus $from,
        RequestStatus $to,
        User $actor,
        ?string $comment
    ): void {
        $notification = new RequestStatusChangedNotification($request, $from, $to, $actor, $comment);

        // Ensure relationships are available
        $request->loadMissing(['requester.manager', 'assignee']);

        switch ($to) {
            case RequestStatus::Submitted:
                if ($request->requester?->manager) {
                    $request->requester->manager->notify($notification);
                }
                break;

            case RequestStatus::Approved:
                if ($request->requester) {
                    $request->requester->notify($notification);
                }
                // Notify operations users
                $operationsUsers = User::where('role', UserRole::Operations->value)->get();
                if ($operationsUsers->isNotEmpty()) {
                    Notification::send($operationsUsers, $notification);
                }
                break;

            case RequestStatus::Rejected:
            case RequestStatus::Returned:
                if ($request->requester) {
                    $request->requester->notify($notification);
                }
                break;

            case RequestStatus::Assigned:
                if ($request->assignee) {
                    $request->assignee->notify($notification);
                }
                break;

            case RequestStatus::Completed:
                if ($request->requester) {
                    $request->requester->notify($notification);
                }
                break;

            default:
                break;
        }
    }
}
