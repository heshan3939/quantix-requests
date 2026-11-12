<?php

namespace Tests\Unit;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ServiceRequestPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ServiceRequestPolicy;
    }

    public function test_view_allowed_for_requester_their_manager_and_operations(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);
        $otherManager = User::factory()->manager()->create();
        $otherEmployee = User::factory()->employee()->create();
        $operations = User::factory()->operations()->create();

        $request = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        // Requester can view
        $this->assertTrue($this->policy->view($employee, $request));

        // Their direct manager can view
        $this->assertTrue($this->policy->view($manager, $request));

        // Operations user can view
        $this->assertTrue($this->policy->view($operations, $request));

        // Other manager cannot view
        $this->assertFalse($this->policy->view($otherManager, $request));

        // Other unrelated employee cannot view
        $this->assertFalse($this->policy->view($otherEmployee, $request));
    }

    public function test_update_allowed_only_for_requester_while_draft_or_returned(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);
        $otherEmployee = User::factory()->employee()->create();

        $draftRequest = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Draft,
        ]);

        $returnedRequest = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Returned,
        ]);

        $submittedRequest = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        // Owner can update draft and returned
        $this->assertTrue($this->policy->update($employee, $draftRequest));
        $this->assertTrue($this->policy->update($employee, $returnedRequest));

        // Owner cannot update once submitted
        $this->assertFalse($this->policy->update($employee, $submittedRequest));

        // Another employee cannot update owner's draft
        $this->assertFalse($this->policy->update($otherEmployee, $draftRequest));
    }

    public function test_submit_allowed_only_for_requester_when_draft_or_returned(): void
    {
        $employee = User::factory()->employee()->create();
        $otherEmployee = User::factory()->employee()->create();

        $draft = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Draft,
        ]);

        $returned = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Returned,
        ]);

        $approved = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Approved,
        ]);

        $this->assertTrue($this->policy->submit($employee, $draft));
        $this->assertTrue($this->policy->submit($employee, $returned));
        $this->assertFalse($this->policy->submit($employee, $approved));
        $this->assertFalse($this->policy->submit($otherEmployee, $draft));
    }

    public function test_decide_allowed_only_for_direct_reports_manager_on_submitted_status(): void
    {
        $manager = User::factory()->manager()->create();
        $otherManager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);

        $submitted = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $draft = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Draft,
        ]);

        // Own manager can decide on submitted
        $this->assertTrue($this->policy->decide($manager, $submitted));

        // Own manager cannot decide if not submitted (e.g. draft)
        $this->assertFalse($this->policy->decide($manager, $draft));

        // Other manager cannot decide on submitted
        $this->assertFalse($this->policy->decide($otherManager, $submitted));

        // Employee cannot decide on own request
        $this->assertFalse($this->policy->decide($employee, $submitted));
    }

    public function test_assign_allowed_only_for_operations_on_approved_requests(): void
    {
        $operations = User::factory()->operations()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $approved = ServiceRequest::factory()->create(['status' => RequestStatus::Approved]);
        $submitted = ServiceRequest::factory()->create(['status' => RequestStatus::Submitted]);

        // Operations can assign approved request
        $this->assertTrue($this->policy->assign($operations, $approved));

        // Operations cannot assign submitted request
        $this->assertFalse($this->policy->assign($operations, $submitted));

        // Manager or employee cannot assign
        $this->assertFalse($this->policy->assign($manager, $approved));
        $this->assertFalse($this->policy->assign($employee, $approved));
    }

    public function test_change_status_allowed_only_for_operations_when_assigned_or_in_progress(): void
    {
        $operations = User::factory()->operations()->create();
        $manager = User::factory()->manager()->create();

        $assigned = ServiceRequest::factory()->create(['status' => RequestStatus::Assigned]);
        $inProgress = ServiceRequest::factory()->create(['status' => RequestStatus::InProgress]);
        $draft = ServiceRequest::factory()->create(['status' => RequestStatus::Draft]);

        // Operations can change status for assigned and in_progress
        $this->assertTrue($this->policy->changeStatus($operations, $assigned));
        $this->assertTrue($this->policy->changeStatus($operations, $inProgress));

        // Operations cannot change status when draft
        $this->assertFalse($this->policy->changeStatus($operations, $draft));

        // Manager cannot change status
        $this->assertFalse($this->policy->changeStatus($manager, $assigned));
    }

    public function test_rejected_and_completed_cannot_be_changed_by_anyone(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create(['manager_id' => $manager->id]);
        $operations = User::factory()->operations()->create();

        $rejected = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Rejected,
        ]);

        $completed = ServiceRequest::factory()->create([
            'requester_id' => $employee->id,
            'status' => RequestStatus::Completed,
        ]);

        foreach ([$rejected, $completed] as $terminalRequest) {
            foreach ([$employee, $manager, $operations] as $user) {
                $this->assertFalse($this->policy->update($user, $terminalRequest));
                $this->assertFalse($this->policy->submit($user, $terminalRequest));
                $this->assertFalse($this->policy->decide($user, $terminalRequest));
                $this->assertFalse($this->policy->assign($user, $terminalRequest));
                $this->assertFalse($this->policy->changeStatus($user, $terminalRequest));
                $this->assertFalse($this->policy->comment($user, $terminalRequest));
            }
        }
    }
}
