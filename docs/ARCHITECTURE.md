# Architecture & Key Technical Decisions

This document outlines the architectural decisions, trade-offs, and design principles implemented in the Quantix Service Requests system.

---

## 1. Centralized Workflow Service (`RequestWorkflow`)

### Decision
All state changes across the entire lifecycle of a service request pass strictly through a single service class: `App\Services\RequestWorkflow::transition()`. Controllers and console commands never update the `status` column directly.

### Trade-offs & Advantages
- **Single Source of Truth**: The state machine graph is defined once in `RequestWorkflow::ALLOWED_TRANSITIONS`. Any illegal status jump is blocked at the core domain layer, avoiding duplicated validation rules across multiple controllers.
- **Transactional Consistency (`DB::transaction`)**: Every transition updates the request status and creates a corresponding `request_activities` audit entry inside the same database transaction. An audit log can never exist without the state update, and a state update can never succeed without an audit log entry.
- **Self-Rendering Custom Exceptions**: Disallowed moves throw an `InvalidTransitionException`. By implementing a `render()` method, the exception automatically formats as a consistent 422 JSON response without requiring repetitive `try-catch` blocks in controllers.

---

## 2. Separation of Authorization (Policies) vs. Business Rules (Workflow)

### Decision
- **Policies (`ServiceRequestPolicy`)** answer: *"Who is allowed to trigger this action?"*
- **Workflow (`RequestWorkflow`)** answers: *"Is this status transition valid in the lifecycle?"*

### Trade-offs & Advantages
- **Decoupled Responsibilities**: A manager might be authorized to decide on a direct report's request, but if the request is already in `draft` or `approved`, the policy/workflow boundaries prevent illegal operations.
- **Thin Controllers**: Controllers simply call `Gate::authorize(...)` and then delegate execution to `RequestWorkflow->transition(...)`. Controllers contain zero business or permission logic.

---

## 3. Backed String Enums for Roles and Statuses

### Decision
PHP 8.2 backed string enums are used for `UserRole` (`employee`, `manager`, `operations`) and `RequestStatus` (`draft`, `submitted`, `returned`, `approved`, `rejected`, `assigned`, `in_progress`, `completed`).

### Trade-offs & Advantages
- **Type Safety**: Replaces "magic strings" with compile-time constants checked by Larastan.
- **Eloquent Casting**: Eloquent automatically casts database column strings to and from enum instances (`casts(): ['status' => RequestStatus::class, 'role' => UserRole::class]`).
- **Portability**: Storing backed strings in the database avoids MySQL ENUM alter-table migration overhead when adding new statuses in the future.

---

## 4. Audit Log Design (`request_activities`)

### Decision
Status transitions are tracked via an append-only `request_activities` table recording `service_request_id`, `user_id`, `from_status`, `to_status`, `comment`, and `created_at`.

### Trade-offs & Advantages
- **Append-Only Immutability**: Historical records are never updated or overwritten. This provides a complete chronological audit trail visible directly on `GET /api/requests/{id}`.
- **Separate from Comments**: General collaboration comments live in `request_comments`, while workflow state transitions live in `request_activities`. This cleanly separates casual conversation from formal lifecycle audits.

---

## 5. Performance & Prevention of N+1 Queries

### Decision
- **Index Scoping**: `GET /api/requests` eager loads `['requester', 'assignee']`. Scoping by role uses `whereHas()` or `whereIn()` at the SQL database layer.
- **Detailed View**: `GET /api/requests/{id}` eager loads `['requester', 'assignee', 'comments.user', 'activities.user']`.
- **Database Indexing**: Indexes are placed on `status`, `requester_id`, `assigned_to`, and `manager_id` to guarantee high throughput during dashboard filtering and list pagination.

