# Quantix Service Requests API

A robust, production-ready internal service request management API built with **Laravel 12**, **PHP 8.2+**, **MySQL**, and **Laravel Sanctum**.

---

## 1. System Requirements

- PHP 8.2 or higher (with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml` extensions enabled)
- Composer 2.x
- MySQL 8.x / MariaDB 10.x
- Git

---

## 2. Setup & Installation

### Step 1: Clone the repository
```bash
git clone <repository-url>
cd quantix-requests
```

### Step 2: Install PHP dependencies
```bash
composer install
```

### Step 3: Environment Configuration
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Generate the application key:
```bash
php artisan key:generate
```

### Step 4: Configure MySQL Databases
Create your application database and test database in MySQL:
```sql
CREATE DATABASE quantix_requests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE quantix_requests_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update `.env` with your MySQL credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quantix_requests
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### Step 5: Migrate and Seed Database
Run migrations and load realistic test data with a single command:
```bash
php artisan migrate:fresh --seed
```

---

## 3. Running the Application & Queue Worker

### Start Local Web Server
```bash
php artisan serve
```
The API is available at `http://127.0.0.1:8000`.

### Run the Queue Worker
Notifications for lifecycle state changes implement `ShouldQueue` and use the database driver. Run the worker in a separate terminal:
```bash
# In development (automatically reflects code changes)
php artisan queue:listen

# In production
php artisan queue:work --tries=3 --timeout=60
```

---

## 4. Seeded User Credentials

All seeded accounts use the password: `password`.

| Role | Name | Email | Password | Reports To |
| :--- | :--- | :--- | :--- | :--- |
| **Manager** | Michael Scott | `manager@quantix.test` | `password` | *None* |
| **Employee** | Jim Halpert | `employee1@quantix.test` | `password` | Michael Scott |
| **Employee** | Pam Beesly | `employee2@quantix.test` | `password` | Michael Scott |
| **Operations** | Dwight Schrute | `ops1@quantix.test` | `password` | *None* |
| **Operations** | Stanley Hudson | `ops2@quantix.test` | `password` | *None* |

---

## 5. Running Tests & Code Quality Tools

### Run Feature & Unit Tests
Tests run against `quantix_requests_test` using MySQL transactions (`RefreshDatabase`):
```bash
php artisan test
```

### Code Style (Laravel Pint)
```bash
vendor/bin/pint --test
```

### Static Analysis (Larastan Level 5)
```bash
vendor/bin/phpstan analyse
```

---

## 6. API Endpoints Overview

| Method | Endpoint | Description | Authorized Roles |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/login` | Authenticate and obtain Sanctum bearer token | Public |
| `GET` | `/api/user` | Get authenticated user info | Authenticated |
| `GET` | `/api/requests` | List requests (scoped by role, with search, filters, pagination) | Authenticated |
| `POST` | `/api/requests` | Create request (as draft or submitted directly) | Employee / Any authenticated |
| `GET` | `/api/requests/{id}` | View request with full activity history & comments | Requester, Manager, Operations |
| `PUT` | `/api/requests/{id}` | Edit request details while `draft` or `returned` | Requester |
| `POST` | `/api/requests/{id}/submit` | Submit draft or returned request | Requester |
| `POST` | `/api/requests/{id}/decision` | Approve, reject, or return request (comment required for reject/return) | Direct Manager |
| `POST` | `/api/requests/{id}/assign` | Assign approved request to operations user | Operations |
| `POST` | `/api/requests/{id}/status` | Move status to `in_progress` or `completed` | Operations |
| `POST` | `/api/requests/{id}/comments` | Add comment to request | Allowed Viewers (non-terminal) |

---

## 7. Assumptions Made Where the Brief Was Silent

1. **Delete Behavior**: `nullOnDelete` is used for `manager_id` on users and `assigned_to` on requests to preserve request history if a user is deactivated. `cascadeOnDelete` is applied to comments and activities if a request is permanently pruned.
2. **Operations Queue Scope**: On `GET /api/requests`, operations users see requests with status `approved`, `assigned`, `in_progress`, and `completed`. Drafts and unapproved submitted requests remain within the employee-manager hierarchy.
3. **Commenting Authorization**: Anyone who is authorized to view a request (`view` policy) can comment on it, provided the request has not reached a terminal state (`rejected` or `completed`).
4. **Mandatory Transition Comments**: Rejections and returns require an explanatory comment in `DecisionRequest` to provide actionable feedback to the employee. Approvals and assignments allow optional comments.
5. **Notification Channels**: Notifications implement `ShouldQueue` and store structured records in the MySQL `notifications` table (`database` channel) while logging outgoing emails via `mail`.

---

## 8. What I Would Do Next (Future Improvements)

1. **File Attachments**: Add support for document/receipt uploads (e.g. S3 / local disk) via a dedicated `media` or `request_attachments` relationship.
2. **SLA & Deadlines**: Introduce an optional `due_at` or `sla_target_at` column with automated scheduled console commands (`php artisan schedule:run`) warning managers of pending approvals.
3. **Realtime WebSockets**: Implement Laravel Reverb or Pusher broadcasting on private channels so users receive instant UI updates when a request status transitions.
4. **Audit Immutability**: Add database-level triggers or an event subscriber forbidding updates or deletes to the `request_activities` table to guarantee compliance-grade immutability.
