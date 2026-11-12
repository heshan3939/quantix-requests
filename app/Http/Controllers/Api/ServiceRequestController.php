<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\AssignRequest;
use App\Http\Requests\ServiceRequests\ChangeStatusRequest;
use App\Http\Requests\ServiceRequests\DecisionRequest;
use App\Http\Requests\ServiceRequests\IndexServiceRequestRequest;
use App\Http\Requests\ServiceRequests\StoreCommentRequest;
use App\Http\Requests\ServiceRequests\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequests\UpdateServiceRequestRequest;
use App\Http\Resources\RequestCommentResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ServiceRequestController extends Controller
{
    public function __construct(
        protected RequestWorkflow $workflow
    ) {}

    /**
     * GET /api/requests
     * Scoped by role:
     * - Employee: only their own requests
     * - Manager: only requests from their direct reports
     * - Operations: requests from approved and later
     */
    public function index(IndexServiceRequestRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ServiceRequest::query()->with(['requester', 'assignee']);

        // Scope by user role
        if ($user->role === UserRole::Employee) {
            $query->where('requester_id', $user->id);
        } elseif ($user->role === UserRole::Manager) {
            $query->whereHas('requester', function ($q) use ($user) {
                $q->where('manager_id', $user->id);
            });
        } elseif ($user->role === UserRole::Operations) {
            $operationsStatuses = [
                RequestStatus::Approved->value,
                RequestStatus::Assigned->value,
                RequestStatus::InProgress->value,
                RequestStatus::Completed->value,
            ];
            $query->whereIn('status', $operationsStatuses);
        }

        // Search by title
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        // Sorting
        $sortBy = $request->validated('sort_by', 'created_at');
        $sortOrder = $request->validated('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination with metadata
        $perPage = (int) $request->validated('per_page', 15);
        $paginated = $query->paginate($perPage);

        return ServiceRequestResource::collection($paginated);
    }

    /**
     * POST /api/requests
     * Create as draft or submit directly.
     */
    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        $user = $request->user();

        $serviceRequest = ServiceRequest::create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'category' => $request->validated('category'),
            'priority' => $request->validated('priority', 'medium'),
            'status' => RequestStatus::Draft,
            'requester_id' => $user->id,
        ]);

        if ($request->boolean('submit_immediately')) {
            $this->workflow->transition($serviceRequest, RequestStatus::Submitted, $user, 'Initial submission');
        }

        $serviceRequest->load(['requester', 'assignee']);

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/requests/{id}
     * Include comments and activity history.
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('view', $serviceRequest);

        $serviceRequest->load([
            'requester',
            'assignee',
            'comments.user',
            'activities.user',
        ]);

        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * PUT /api/requests/{id}
     * Update details while draft or returned.
     */
    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('update', $serviceRequest);

        $serviceRequest->update($request->validated());
        $serviceRequest->load(['requester', 'assignee']);

        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * POST /api/requests/{id}/submit
     * Submit a draft or returned request.
     */
    public function submit(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('submit', $serviceRequest);

        $updated = $this->workflow->transition(
            $serviceRequest,
            RequestStatus::Submitted,
            request()->user(),
            'Submitted by employee'
        );

        $updated->load(['requester', 'assignee']);

        return new ServiceRequestResource($updated);
    }

    /**
     * POST /api/requests/{id}/decision
     * Manager decides: approve, reject, or return with comment.
     */
    public function decision(DecisionRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('decide', $serviceRequest);

        $targetStatus = $request->toStatus();
        $comment = $request->validated('comment');

        $updated = $this->workflow->transition(
            $serviceRequest,
            $targetStatus,
            $request->user(),
            $comment
        );

        $updated->load(['requester', 'assignee']);

        return new ServiceRequestResource($updated);
    }

    /**
     * POST /api/requests/{id}/assign
     * Operations user assigns request to an operations personnel.
     */
    public function assign(AssignRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('assign', $serviceRequest);

        $serviceRequest->assigned_to = $request->validated('assigned_to');
        $serviceRequest->save();

        $updated = $this->workflow->transition(
            $serviceRequest,
            RequestStatus::Assigned,
            $request->user(),
            $request->validated('comment')
        );

        $updated->load(['requester', 'assignee']);

        return new ServiceRequestResource($updated);
    }

    /**
     * POST /api/requests/{id}/status
     * Operations user advances status to in_progress or completed.
     */
    public function status(ChangeStatusRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('changeStatus', $serviceRequest);

        $targetStatus = $request->toStatus();

        $updated = $this->workflow->transition(
            $serviceRequest,
            $targetStatus,
            $request->user(),
            $request->validated('comment')
        );

        $updated->load(['requester', 'assignee']);

        return new ServiceRequestResource($updated);
    }

    /**
     * POST /api/requests/{id}/comments
     * Add a comment to the request.
     */
    public function comment(StoreCommentRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('comment', $serviceRequest);

        $comment = $serviceRequest->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load('user');

        return (new RequestCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }
}
