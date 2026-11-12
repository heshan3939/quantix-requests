<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestStatusChangedNotification;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private RequestWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new RequestWorkflow;
        Notification::fake();
    }

    public function test_submitted_notifies_requesters_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Draft,
        ]);

        $this->workflow->transition($request, RequestStatus::Submitted, $employee);

        Notification::assertSentTo($manager, RequestStatusChangedNotification::class, function ($n) use ($request) {
            return $n->toStatus === RequestStatus::Submitted && $n->serviceRequest->id === $request->id;
        });
    }

    public function test_approved_notifies_requester_and_operations(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);
        $ops1 = User::factory()->operations()->create();
        $ops2 = User::factory()->operations()->create();

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $this->workflow->transition($request, RequestStatus::Approved, $manager);

        Notification::assertSentTo($employee, RequestStatusChangedNotification::class);
        Notification::assertSentTo($ops1, RequestStatusChangedNotification::class);
        Notification::assertSentTo($ops2, RequestStatusChangedNotification::class);
    }

    public function test_rejected_and_returned_notify_requester(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $this->workflow->transition($request, RequestStatus::Returned, $manager, 'Fix info');
        Notification::assertSentTo($employee, RequestStatusChangedNotification::class);

        $request2 = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $this->workflow->transition($request2, RequestStatus::Rejected, $manager, 'Denied');
        Notification::assertSentTo($employee, RequestStatusChangedNotification::class);
    }

    public function test_assigned_notifies_assignee(): void
    {
        $ops = User::factory()->operations()->create();
        $employee = User::factory()->employee()->create();

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'assigned_to' => $ops->id,
            'status' => RequestStatus::Approved,
        ]);

        $this->workflow->transition($request, RequestStatus::Assigned, $ops);

        Notification::assertSentTo($ops, RequestStatusChangedNotification::class);
    }

    public function test_completed_notifies_requester(): void
    {
        $ops = User::factory()->operations()->create();
        $employee = User::factory()->employee()->create();

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'assigned_to' => $ops->id,
            'status' => RequestStatus::InProgress,
        ]);

        $this->workflow->transition($request, RequestStatus::Completed, $ops);

        Notification::assertSentTo($employee, RequestStatusChangedNotification::class);
    }
}
