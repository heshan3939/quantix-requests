<?php

namespace Tests\Unit;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidTransitionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private RequestWorkflow $workflow;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new RequestWorkflow;
        $this->actor = User::factory()->create(['role' => UserRole::Employee]);
    }

    public function test_can_transition_from_draft_to_submitted(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Draft]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Submitted));

        $updated = $this->workflow->transition($request, RequestStatus::Submitted, $this->actor, 'Submitting request');

        $this->assertEquals(RequestStatus::Submitted, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'user_id' => $this->actor->id,
            'from_status' => RequestStatus::Draft->value,
            'to_status' => RequestStatus::Submitted->value,
            'comment' => 'Submitting request',
        ]);
    }

    public function test_can_transition_from_submitted_to_approved(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Submitted]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Approved));

        $updated = $this->workflow->transition($request, RequestStatus::Approved, $this->actor, 'Approved by manager');

        $this->assertEquals(RequestStatus::Approved, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Submitted->value,
            'to_status' => RequestStatus::Approved->value,
        ]);
    }

    public function test_can_transition_from_submitted_to_rejected(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Submitted]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Rejected));

        $updated = $this->workflow->transition($request, RequestStatus::Rejected, $this->actor, 'Budget exceeded');

        $this->assertEquals(RequestStatus::Rejected, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Submitted->value,
            'to_status' => RequestStatus::Rejected->value,
            'comment' => 'Budget exceeded',
        ]);
    }

    public function test_can_transition_from_submitted_to_returned(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Submitted]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Returned));

        $updated = $this->workflow->transition($request, RequestStatus::Returned, $this->actor, 'Please add more details');

        $this->assertEquals(RequestStatus::Returned, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Submitted->value,
            'to_status' => RequestStatus::Returned->value,
            'comment' => 'Please add more details',
        ]);
    }

    public function test_can_transition_from_returned_to_submitted(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Returned]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Submitted));

        $updated = $this->workflow->transition($request, RequestStatus::Submitted, $this->actor, 'Added requested info');

        $this->assertEquals(RequestStatus::Submitted, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Returned->value,
            'to_status' => RequestStatus::Submitted->value,
        ]);
    }

    public function test_can_transition_from_approved_to_assigned(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Approved]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Assigned));

        $updated = $this->workflow->transition($request, RequestStatus::Assigned, $this->actor);

        $this->assertEquals(RequestStatus::Assigned, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Approved->value,
            'to_status' => RequestStatus::Assigned->value,
        ]);
    }

    public function test_can_transition_from_assigned_to_in_progress(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Assigned]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::InProgress));

        $updated = $this->workflow->transition($request, RequestStatus::InProgress, $this->actor);

        $this->assertEquals(RequestStatus::InProgress, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::Assigned->value,
            'to_status' => RequestStatus::InProgress->value,
        ]);
    }

    public function test_can_transition_from_in_progress_to_completed(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::InProgress]);

        $this->assertTrue($this->workflow->canTransition($request, RequestStatus::Completed));

        $updated = $this->workflow->transition($request, RequestStatus::Completed, $this->actor, 'Work finished');

        $this->assertEquals(RequestStatus::Completed, $updated->status);
        $this->assertDatabaseHas('request_activities', [
            'service_request_id' => $request->id,
            'from_status' => RequestStatus::InProgress->value,
            'to_status' => RequestStatus::Completed->value,
            'comment' => 'Work finished',
        ]);
    }

    public function test_cannot_transition_from_draft_directly_to_approved(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Draft]);

        $this->assertFalse($this->workflow->canTransition($request, RequestStatus::Approved));

        $this->expectException(InvalidTransitionException::class);
        $this->workflow->transition($request, RequestStatus::Approved, $this->actor);
    }

    public function test_cannot_transition_from_draft_directly_to_completed(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Draft]);

        $this->assertFalse($this->workflow->canTransition($request, RequestStatus::Completed));

        $this->expectException(InvalidTransitionException::class);
        $this->workflow->transition($request, RequestStatus::Completed, $this->actor);
    }

    public function test_rejected_status_is_terminal(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Rejected]);

        foreach (RequestStatus::cases() as $status) {
            $this->assertFalse($this->workflow->canTransition($request, $status));
        }

        $this->expectException(InvalidTransitionException::class);
        $this->workflow->transition($request, RequestStatus::Submitted, $this->actor);
    }

    public function test_completed_status_is_terminal(): void
    {
        $request = ServiceRequest::factory()->create(['status' => RequestStatus::Completed]);

        foreach (RequestStatus::cases() as $status) {
            $this->assertFalse($this->workflow->canTransition($request, $status));
        }

        $this->expectException(InvalidTransitionException::class);
        $this->workflow->transition($request, RequestStatus::InProgress, $this->actor);
    }

    public function test_invalid_transition_renders_correct_json_response(): void
    {
        $exception = new InvalidTransitionException(RequestStatus::Draft, RequestStatus::Completed);
        $response = $exception->render(request());

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('invalid_status_transition', $data['error']);
        $this->assertEquals('draft', $data['from']);
        $this->assertEquals('completed', $data['to']);
    }
}
