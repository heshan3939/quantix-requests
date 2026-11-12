<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    private User $otherManager;

    private User $otherEmployee;

    private User $ops1;

    private User $ops2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->employee = User::factory()->employee()->create(['manager_id' => $this->manager->id]);

        $this->otherManager = User::factory()->manager()->create();
        $this->otherEmployee = User::factory()->employee()->create(['manager_id' => $this->otherManager->id]);

        $this->ops1 = User::factory()->operations()->create();
        $this->ops2 = User::factory()->operations()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Happy Path Per Role
    |--------------------------------------------------------------------------
    */

    public function test_happy_path_full_lifecycle(): void
    {
        // 1. Employee creates a draft request
        $createResponse = $this->actingAs($this->employee)
            ->postJson('/api/requests', [
                'title' => 'Ergonomic Desk',
                'description' => 'Need ergonomic setup.',
                'category' => 'facilities',
                'priority' => 'medium',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.title', 'Ergonomic Desk')
            ->assertJsonPath('data.status', RequestStatus::Draft->value);

        $requestId = $createResponse->json('data.id');

        // 2. Employee submits the request
        $submitResponse = $this->actingAs($this->employee)
            ->postJson("/api/requests/{$requestId}/submit");

        $submitResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::Submitted->value);

        // 3. Manager approves the request
        $approveResponse = $this->actingAs($this->manager)
            ->postJson("/api/requests/{$requestId}/decision", [
                'decision' => 'approved',
                'comment' => 'Approved budget for desk.',
            ]);

        $approveResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::Approved->value);

        // 4. Operations assigns the request
        $assignResponse = $this->actingAs($this->ops1)
            ->postJson("/api/requests/{$requestId}/assign", [
                'assigned_to' => $this->ops2->id,
                'comment' => 'Assigned to operations specialist.',
            ]);

        $assignResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::Assigned->value)
            ->assertJsonPath('data.assignee.id', $this->ops2->id);

        // 5. Operations moves status to in_progress
        $progressResponse = $this->actingAs($this->ops2)
            ->postJson("/api/requests/{$requestId}/status", [
                'status' => 'in_progress',
                'comment' => 'Procurement started.',
            ]);

        $progressResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::InProgress->value);

        // 6. Operations completes the request
        $completeResponse = $this->actingAs($this->ops2)
            ->postJson("/api/requests/{$requestId}/status", [
                'status' => 'completed',
                'comment' => 'Desk delivered and assembled.',
            ]);

        $completeResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::Completed->value);

        // 7. Verify show endpoint includes comments and complete activity trail
        $showResponse = $this->actingAs($this->employee)
            ->getJson("/api/requests/{$requestId}");

        $showResponse->assertOk()
            ->assertJsonPath('data.status', RequestStatus::Completed->value)
            ->assertJsonCount(5, 'data.activities'); // draft->sub, sub->app, app->ass, ass->in_prog, in_prog->comp
    }

    public function test_employee_can_create_and_submit_directly(): void
    {
        $response = $this->actingAs($this->employee)
            ->postJson('/api/requests', [
                'title' => 'Software License',
                'description' => 'Docker Desktop subscription',
                'category' => 'software',
                'priority' => 'high',
                'submit_immediately' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', RequestStatus::Submitted->value);
    }

    public function test_employee_can_add_comment_to_own_request(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson("/api/requests/{$request->id}/comments", [
                'body' => 'Adding more details regarding serial number.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Adding more details regarding serial number.')
            ->assertJsonPath('data.user.id', $this->employee->id);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Forbidden Actions (Must return 403)
    |--------------------------------------------------------------------------
    */

    public function test_employee_cannot_make_decision_returns_403(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $response = $this->actingAs($this->employee)
            ->postJson("/api/requests/{$request->id}/decision", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_manager_cannot_decide_on_other_managers_direct_report_returns_403(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->otherEmployee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/requests/{$request->id}/decision", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_employee_cannot_edit_approved_request_returns_403(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Approved,
        ]);

        $response = $this->actingAs($this->employee)
            ->putJson("/api/requests/{$request->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_non_operations_user_cannot_assign_returns_403(): void
    {
        $request = ServiceRequest::factory()->create([
            'status' => RequestStatus::Approved,
        ]);

        // Manager attempt to assign
        $response = $this->actingAs($this->manager)
            ->postJson("/api/requests/{$request->id}/assign", [
                'assigned_to' => $this->ops1->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonStructure(['message', 'errors']);

        // Employee attempt to assign
        $response2 = $this->actingAs($this->employee)
            ->postJson("/api/requests/{$request->id}/assign", [
                'assigned_to' => $this->ops1->id,
            ]);

        $response2->assertStatus(403);
    }

    public function test_employee_cannot_view_unrelated_employee_request_returns_403(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->otherEmployee->id,
            'status' => RequestStatus::Draft,
        ]);

        $response = $this->actingAs($this->employee)
            ->getJson("/api/requests/{$request->id}");

        $response->assertStatus(403)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_no_one_can_mutate_terminal_rejected_or_completed_requests_returns_403(): void
    {
        $rejected = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Rejected,
        ]);

        $completed = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Completed,
        ]);

        foreach ([$rejected, $completed] as $terminal) {
            // Edit attempt
            $this->actingAs($this->employee)
                ->putJson("/api/requests/{$terminal->id}", ['title' => 'New Title'])
                ->assertStatus(403);

            // Decision attempt
            $this->actingAs($this->manager)
                ->postJson("/api/requests/{$terminal->id}/decision", ['decision' => 'approved'])
                ->assertStatus(403);

            // Assign attempt
            $this->actingAs($this->ops1)
                ->postJson("/api/requests/{$terminal->id}/assign", ['assigned_to' => $this->ops2->id])
                ->assertStatus(403);

            // Status change attempt
            $this->actingAs($this->ops1)
                ->postJson("/api/requests/{$terminal->id}/status", ['status' => 'in_progress'])
                ->assertStatus(403);

            // Comment attempt
            $this->actingAs($this->employee)
                ->postJson("/api/requests/{$terminal->id}/comments", ['body' => 'Cannot comment'])
                ->assertStatus(403);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Invalid Transitions (Must return 422)
    |--------------------------------------------------------------------------
    */

    public function test_invalid_transition_returns_422(): void
    {
        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Draft,
        ]);

        // Attempting to jump directly from draft to approved via service logic or illegal move
        // When manager decides on a non-submitted request
        // First test validation failure when comment is missing for rejection
        $submitted = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Submitted,
        ]);

        $missingCommentResp = $this->actingAs($this->manager)
            ->postJson("/api/requests/{$submitted->id}/decision", [
                'decision' => 'rejected',
                // comment omitted
            ]);

        $missingCommentResp->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);

        // When operations attempts an invalid status change: e.g. status moving to completed from assigned
        $assigned = ServiceRequest::factory()->create([
            'assigned_to' => $this->ops1->id,
            'status' => RequestStatus::Assigned,
        ]);

        $invalidMoveResp = $this->actingAs($this->ops1)
            ->postJson("/api/requests/{$assigned->id}/status", [
                'status' => 'completed',
            ]);

        $invalidMoveResp->assertStatus(422)
            ->assertJsonPath('error', 'invalid_status_transition');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Notifications Dispatched on Status Change
    |--------------------------------------------------------------------------
    */

    public function test_notification_dispatched_on_status_change(): void
    {
        Notification::fake();

        $request = ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'status' => RequestStatus::Draft,
        ]);

        $this->actingAs($this->employee)
            ->postJson("/api/requests/{$request->id}/submit")
            ->assertOk();

        Notification::assertSentTo(
            $this->manager,
            RequestStatusChangedNotification::class,
            function (RequestStatusChangedNotification $notification) use ($request) {
                return $notification->serviceRequest->id === $request->id
                    && $notification->toStatus === RequestStatus::Submitted;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Authentication (401), Filtering, Search & Pagination
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/requests');

        $response->assertStatus(401)
            ->assertJsonStructure(['message', 'errors' => ['auth']]);
    }

    public function test_list_scoped_by_employee(): void
    {
        // 2 requests for this employee, 1 for other employee
        ServiceRequest::factory()->create(['requester_id' => $this->employee->id, 'title' => 'Alpha Desk']);
        ServiceRequest::factory()->create(['requester_id' => $this->employee->id, 'title' => 'Beta Chair']);
        ServiceRequest::factory()->create(['requester_id' => $this->otherEmployee->id, 'title' => 'Gamma Monitor']);

        $response = $this->actingAs($this->employee)->getJson('/api/requests');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_scoped_by_manager(): void
    {
        // Requests by direct report vs other report
        ServiceRequest::factory()->create(['requester_id' => $this->employee->id, 'title' => 'Direct Report Request']);
        ServiceRequest::factory()->create(['requester_id' => $this->otherEmployee->id, 'title' => 'Other Manager Report']);

        $response = $this->actingAs($this->manager)->getJson('/api/requests');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Direct Report Request');
    }

    public function test_list_scoped_by_operations_user(): void
    {
        // Operations sees approved and later, but not draft or submitted
        ServiceRequest::factory()->create(['status' => RequestStatus::Draft]);
        ServiceRequest::factory()->create(['status' => RequestStatus::Submitted]);
        ServiceRequest::factory()->create(['status' => RequestStatus::Approved]);
        ServiceRequest::factory()->create(['status' => RequestStatus::Assigned]);
        ServiceRequest::factory()->create(['status' => RequestStatus::InProgress]);
        ServiceRequest::factory()->create(['status' => RequestStatus::Completed]);

        $response = $this->actingAs($this->ops1)->getJson('/api/requests');

        $response->assertOk()
            ->assertJsonCount(4, 'data'); // approved, assigned, in_progress, completed
    }

    public function test_search_and_pagination(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            ServiceRequest::factory()->create([
                'requester_id' => $this->employee->id,
                'title' => "Special Laptop {$i}",
            ]);
        }

        // Test pagination per_page = 4
        $paginated = $this->actingAs($this->employee)
            ->getJson('/api/requests?per_page=4');

        $paginated->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.per_page', 4)
            ->assertJsonPath('meta.total', 10);

        // Test search filter
        ServiceRequest::factory()->create([
            'requester_id' => $this->employee->id,
            'title' => 'Unique Gadget X',
        ]);

        $searchResp = $this->actingAs($this->employee)
            ->getJson('/api/requests?search=Gadget');

        $searchResp->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unique Gadget X');
    }
}
