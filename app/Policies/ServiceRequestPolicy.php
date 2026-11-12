<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    /**
     * View is allowed for the requester, their manager, and operations users.
     */
    public function view(User $user, ServiceRequest $request): bool
    {
        // 1. Requester can view their own requests
        if ($user->id === $request->requester_id) {
            return true;
        }

        // 2. Operations users can view any request
        if ($user->role === UserRole::Operations) {
            return true;
        }

        // 3. Manager can view requests from their direct reports
        if ($user->role === UserRole::Manager) {
            return $request->requester?->manager_id === $user->id;
        }

        return false;
    }

    /**
     * Employees can edit only their own requests while draft or returned.
     * Terminal requests (rejected, completed) cannot be changed by anyone.
     */
    public function update(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        $canEditStatus = in_array($request->status, [RequestStatus::Draft, RequestStatus::Returned], true);

        return $user->id === $request->requester_id && $canEditStatus;
    }

    /**
     * Submit request: employees can submit their own draft or returned requests.
     */
    public function submit(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        $canSubmitStatus = in_array($request->status, [RequestStatus::Draft, RequestStatus::Returned], true);

        return $user->id === $request->requester_id && $canSubmitStatus;
    }

    /**
     * A manager can decide (approve, reject, return) only on requests from their own direct reports.
     * Must be in 'submitted' status and not terminal.
     */
    public function decide(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        if ($user->role !== UserRole::Manager || $request->status !== RequestStatus::Submitted) {
            return false;
        }

        return $request->requester?->manager_id === $user->id;
    }

    /**
     * Only operations users can assign (from approved status).
     */
    public function assign(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        return $user->role === UserRole::Operations && $request->status === RequestStatus::Approved;
    }

    /**
     * Status changes once assigned:
     * Only operations users can start (assigned -> in_progress) and complete (in_progress -> completed).
     */
    public function changeStatus(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        if ($user->role !== UserRole::Operations) {
            return false;
        }

        return in_array($request->status, [RequestStatus::Assigned, RequestStatus::InProgress], true);
    }

    /**
     * Commenting: allowed for anyone who can view the request, provided it is not terminal.
     */
    public function comment(User $user, ServiceRequest $request): bool
    {
        if ($this->isTerminal($request)) {
            return false;
        }

        return $this->view($user, $request);
    }

    /**
     * Helper to verify if request is in a terminal state.
     * Rejected and completed requests cannot be changed by anyone.
     */
    private function isTerminal(ServiceRequest $request): bool
    {
        return in_array($request->status, [RequestStatus::Rejected, RequestStatus::Completed], true);
    }
}
