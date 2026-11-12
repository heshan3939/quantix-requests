<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Create 1 Manager
        $manager = User::create([
            'name' => 'Michael Scott',
            'email' => 'manager@quantix.test',
            'password' => $password,
            'role' => UserRole::Manager,
            'manager_id' => null,
            'email_verified_at' => now(),
        ]);

        // 2. Create 2 Employees (Direct Reports to Manager)
        $employee1 = User::create([
            'name' => 'Jim Halpert',
            'email' => 'employee1@quantix.test',
            'password' => $password,
            'role' => UserRole::Employee,
            'manager_id' => $manager->id,
            'email_verified_at' => now(),
        ]);

        $employee2 = User::create([
            'name' => 'Pam Beesly',
            'email' => 'employee2@quantix.test',
            'password' => $password,
            'role' => UserRole::Employee,
            'manager_id' => $manager->id,
            'email_verified_at' => now(),
        ]);

        // 3. Create 2 Operations users
        $ops1 = User::create([
            'name' => 'Dwight Schrute',
            'email' => 'ops1@quantix.test',
            'password' => $password,
            'role' => UserRole::Operations,
            'manager_id' => null,
            'email_verified_at' => now(),
        ]);

        $ops2 = User::create([
            'name' => 'Stanley Hudson',
            'email' => 'ops2@quantix.test',
            'password' => $password,
            'role' => UserRole::Operations,
            'manager_id' => null,
            'email_verified_at' => now(),
        ]);

        // 4. Seed ~10 Service Requests across all statuses with realistic activities & comments

        // Request 1: Draft
        $r1 = ServiceRequest::create([
            'title' => 'Ergonomic Desk Chair Request',
            'description' => 'Requesting an ergonomic mesh chair due to lower back discomfort during long working sessions.',
            'category' => 'facilities',
            'priority' => 'low',
            'status' => RequestStatus::Draft,
            'requester_id' => $employee1->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $r1->comments()->create([
            'user_id' => $employee1->id,
            'body' => 'Checking catalogue prices for standard office chair options.',
            'created_at' => now()->subDays(10),
        ]);

        // Request 2: Submitted (pending manager decision)
        $r2 = ServiceRequest::create([
            'title' => 'Dual 4K Monitor Setup',
            'description' => 'Need additional external displays to improve productivity when reviewing code diffs and designs.',
            'category' => 'hardware',
            'priority' => 'medium',
            'status' => RequestStatus::Submitted,
            'requester_id' => $employee1->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(6),
        ]);
        $r2->activities()->create([
            'user_id' => $employee1->id,
            'from_status' => RequestStatus::Draft,
            'to_status' => RequestStatus::Submitted,
            'comment' => 'Submitted for managerial review.',
            'created_at' => now()->subDays(6),
        ]);
        $r2->comments()->create([
            'user_id' => $employee1->id,
            'body' => 'I already have the HDMI/DisplayPort adapters ready on my desk.',
            'created_at' => now()->subDays(5),
        ]);

        // Request 3: Returned (manager asked for clarification)
        $r3 = ServiceRequest::create([
            'title' => 'IntelliJ IDEA Ultimate License',
            'description' => 'Requesting a team commercial subscription for advanced profiling and database integration tooling.',
            'category' => 'software',
            'priority' => 'medium',
            'status' => RequestStatus::Returned,
            'requester_id' => $employee2->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(4),
        ]);
        $r3->activities()->createMany([
            [
                'user_id' => $employee2->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Initial submission.',
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Returned,
                'comment' => 'Please confirm if the free community edition or existing VS Code plugins suffice first.',
                'created_at' => now()->subDays(4),
            ],
        ]);
        $r3->comments()->create([
            'user_id' => $manager->id,
            'body' => 'Please attach the pricing breakdown for annual billing vs monthly.',
            'created_at' => now()->subDays(4),
        ]);

        // Request 4: Approved (manager approved, awaiting operations assignment)
        $r4 = ServiceRequest::create([
            'title' => 'Production AWS IAM Read-Only Access',
            'description' => 'Require read-only cloudwatch metrics and S3 bucket inspection permissions for on-call triage.',
            'category' => 'access',
            'priority' => 'high',
            'status' => RequestStatus::Approved,
            'requester_id' => $employee2->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(3),
        ]);
        $r4->activities()->createMany([
            [
                'user_id' => $employee2->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted for on-call rotation readiness.',
                'created_at' => now()->subDays(4),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Approved,
                'comment' => 'Approved for on-call duties.',
                'created_at' => now()->subDays(3),
            ],
        ]);
        $r4->comments()->create([
            'user_id' => $manager->id,
            'body' => 'Operations team, please provision within standard security group guidelines.',
            'created_at' => now()->subDays(3),
        ]);

        // Request 5: Rejected (terminal state)
        $r5 = ServiceRequest::create([
            'title' => 'Flagship Mechanical Keyboard & Custom Keycaps',
            'description' => 'Requesting premium custom artisan mechanical keyboard ($400) for personal aesthetic preferences.',
            'category' => 'hardware',
            'priority' => 'low',
            'status' => RequestStatus::Rejected,
            'requester_id' => $employee1->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(8),
        ]);
        $r5->activities()->createMany([
            [
                'user_id' => $employee1->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted.',
                'created_at' => now()->subDays(8),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Rejected,
                'comment' => 'Exceeds budget allowance for standard peripheral accessories. Standard keyboard will be provided.',
                'created_at' => now()->subDays(8),
            ],
        ]);
        $r5->comments()->create([
            'user_id' => $manager->id,
            'body' => 'You may choose any keyboard from the pre-approved standard IT catalogue.',
            'created_at' => now()->subDays(8),
        ]);

        // Request 6: Assigned (assigned to ops1, pending start)
        $r6 = ServiceRequest::create([
            'title' => 'MacBook Pro RAM Upgrade (16GB -> 32GB)',
            'description' => 'Local Docker containers and integration test suites are hitting swap memory limits.',
            'category' => 'hardware',
            'priority' => 'high',
            'status' => RequestStatus::Assigned,
            'requester_id' => $employee1->id,
            'assigned_to' => $ops1->id,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(2),
        ]);
        $r6->activities()->createMany([
            [
                'user_id' => $employee1->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted with memory diagnostic report.',
                'created_at' => now()->subDays(4),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Approved,
                'comment' => 'Approved. Confirmed engineering need.',
                'created_at' => now()->subDays(3),
            ],
            [
                'user_id' => $ops1->id,
                'from_status' => RequestStatus::Approved,
                'to_status' => RequestStatus::Assigned,
                'comment' => 'Assigned to Dwight Schrute for IT hardware procurement.',
                'created_at' => now()->subDays(2),
            ],
        ]);
        $r6->comments()->createMany([
            [
                'user_id' => $ops1->id,
                'body' => 'Order placed with IT supplier. Awaiting shipment arrival.',
                'created_at' => now()->subDays(2),
            ],
        ]);

        // Request 7: In Progress (ops2 actively working)
        $r7 = ServiceRequest::create([
            'title' => 'Office Parking Permit Pass',
            'description' => 'Requesting permanent parking access badge for basement building garage.',
            'category' => 'facilities',
            'priority' => 'low',
            'status' => RequestStatus::InProgress,
            'requester_id' => $employee2->id,
            'assigned_to' => $ops2->id,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(1),
        ]);
        $r7->activities()->createMany([
            [
                'user_id' => $employee2->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted with vehicle registration info.',
                'created_at' => now()->subDays(4),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Approved,
                'comment' => 'Approved.',
                'created_at' => now()->subDays(3),
            ],
            [
                'user_id' => $ops2->id,
                'from_status' => RequestStatus::Approved,
                'to_status' => RequestStatus::Assigned,
                'comment' => 'Assigned to facilities coordinator.',
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => $ops2->id,
                'from_status' => RequestStatus::Assigned,
                'to_status' => RequestStatus::InProgress,
                'comment' => 'Contacted building property management for RFID card encoding.',
                'created_at' => now()->subDays(1),
            ],
        ]);
        $r7->comments()->create([
            'user_id' => $ops2->id,
            'body' => 'Card is being programmed. Should be ready for pickup at reception tomorrow morning.',
            'created_at' => now()->subDays(1),
        ]);

        // Request 8: Completed (terminal state)
        $r8 = ServiceRequest::create([
            'title' => 'GitHub Enterprise Organization Invitation',
            'description' => 'Need membership access to organization repositories and CI/CD pipelines.',
            'category' => 'access',
            'priority' => 'urgent',
            'status' => RequestStatus::Completed,
            'requester_id' => $employee1->id,
            'assigned_to' => $ops1->id,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(3),
        ]);
        $r8->activities()->createMany([
            [
                'user_id' => $employee1->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted during onboarding.',
                'created_at' => now()->subDays(7),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Approved,
                'comment' => 'Approved expedited access.',
                'created_at' => now()->subDays(6),
            ],
            [
                'user_id' => $ops1->id,
                'from_status' => RequestStatus::Approved,
                'to_status' => RequestStatus::Assigned,
                'comment' => 'Assigned to DevSecOps admin.',
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => $ops1->id,
                'from_status' => RequestStatus::Assigned,
                'to_status' => RequestStatus::InProgress,
                'comment' => 'Configuring SSO team mapping.',
                'created_at' => now()->subDays(4),
            ],
            [
                'user_id' => $ops1->id,
                'from_status' => RequestStatus::InProgress,
                'to_status' => RequestStatus::Completed,
                'comment' => 'Invitation sent and accepted. Full repo access verified.',
                'created_at' => now()->subDays(3),
            ],
        ]);
        $r8->comments()->createMany([
            [
                'user_id' => $employee1->id,
                'body' => 'Thank you, access is working smoothly!',
                'created_at' => now()->subDays(3),
            ],
        ]);

        // Request 9: Second Completed Request (Facilities repair)
        $r9 = ServiceRequest::create([
            'title' => 'Fix Air Conditioning in Conference Room B',
            'description' => 'The climate control unit in Conference Room B is blowing ambient air and making clicking sounds.',
            'category' => 'facilities',
            'priority' => 'urgent',
            'status' => RequestStatus::Completed,
            'requester_id' => $employee2->id,
            'assigned_to' => $ops2->id,
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(10),
        ]);
        $r9->activities()->createMany([
            [
                'user_id' => $employee2->id,
                'from_status' => RequestStatus::Draft,
                'to_status' => RequestStatus::Submitted,
                'comment' => 'Submitted before client demo meeting.',
                'created_at' => now()->subDays(12),
            ],
            [
                'user_id' => $manager->id,
                'from_status' => RequestStatus::Submitted,
                'to_status' => RequestStatus::Approved,
                'comment' => 'Approved urgent repair.',
                'created_at' => now()->subDays(11),
            ],
            [
                'user_id' => $ops2->id,
                'from_status' => RequestStatus::Approved,
                'to_status' => RequestStatus::Assigned,
                'comment' => 'HVAC technician scheduled.',
                'created_at' => now()->subDays(11),
            ],
            [
                'user_id' => $ops2->id,
                'from_status' => RequestStatus::Assigned,
                'to_status' => RequestStatus::InProgress,
                'comment' => 'Technician onsite replacing thermostat capacitor.',
                'created_at' => now()->subDays(10),
            ],
            [
                'user_id' => $ops2->id,
                'from_status' => RequestStatus::InProgress,
                'to_status' => RequestStatus::Completed,
                'comment' => 'Thermostat serviced, airflow and cooling verified at 21C.',
                'created_at' => now()->subDays(10),
            ],
        ]);
        $r9->comments()->create([
            'user_id' => $ops2->id,
            'body' => 'Maintenance ticket closed by HVAC vendor.',
            'created_at' => now()->subDays(10),
        ]);

        // Request 10: Second Submitted Request (pending manager)
        $r10 = ServiceRequest::create([
            'title' => 'DataCamp Enterprise Learning Subscription',
            'description' => 'Requesting access to Python data science and machine learning tracks for professional development.',
            'category' => 'software',
            'priority' => 'low',
            'status' => RequestStatus::Submitted,
            'requester_id' => $employee2->id,
            'assigned_to' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $r10->activities()->create([
            'user_id' => $employee2->id,
            'from_status' => RequestStatus::Draft,
            'to_status' => RequestStatus::Submitted,
            'comment' => 'Submitted for Q4 training allocation.',
            'created_at' => now()->subDays(2),
        ]);
    }
}
