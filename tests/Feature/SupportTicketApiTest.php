<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SupportReferenceDataSeeder;
use Database\Seeders\SupportTicketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupportReferenceDataSeeder::class);
        $this->seed(SupportTicketSeeder::class);
    }

    public function test_user_can_login_fetch_me_and_logout(): void
    {
        User::query()->create([
            'name' => 'Amit',
            'email' => 'amit@example.com',
            'password' => 'password',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'amit@example.com',
            'password' => 'password',
            'deviceName' => 'Angular',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('tokenType', 'Bearer')
            ->assertJsonPath('user.name', 'Amit')
            ->assertJsonStructure(['token', 'tokenType', 'expiresAt', 'user' => ['id', 'name', 'email']]);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.name', 'Amit');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Arka',
            'email' => 'arka@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('tokenType', 'Bearer')
            ->assertJsonPath('user.name', 'Arka')
            ->assertJsonStructure(['token', 'tokenType', 'expiresAt', 'user' => ['id', 'name', 'email']]);
    }

    public function test_setup_routes_require_authentication(): void
    {
        $this->getJson('/api/setup/teams')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_setup_queue_crud_flow(): void
    {
        User::query()->create([
            'name' => 'Amit',
            'email' => 'amit@example.com',
            'password' => 'password',
        ]);
        $token = $this->postJson('/api/auth/login', [
            'email' => 'amit@example.com',
            'password' => 'password',
        ])->json('token');

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->postJson('/api/setup/queues', [
            'id' => 'claims-review',
            'name' => 'Claims Review',
            'teamId' => 'portal-support',
            'isActive' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', 'claims-review')
            ->assertJsonPath('data.teamId', 'portal-support');

        $this->withHeaders($headers)->getJson('/api/setup/queues/claims-review')
            ->assertOk()
            ->assertJsonPath('data.name', 'Claims Review');

        $this->withHeaders($headers)->putJson('/api/setup/queues/claims-review', [
            'name' => 'Claims Review Updated',
            'isActive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Claims Review Updated')
            ->assertJsonPath('data.isActive', false);

        $this->withHeaders($headers)->deleteJson('/api/setup/queues/claims-review')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_reference_data_reads_setup_queue_masters(): void
    {
        $response = $this->getJson('/api/support/reference-data');

        $response
            ->assertOk()
            ->assertJsonFragment(['id' => 'all-open', 'name' => 'All open', 'isActive' => true])
            ->assertJsonStructure([
                'customers',
                'slaPolicies',
                'macros',
            ]);
    }

    public function test_it_returns_a_paginated_ticket_list(): void
    {
        $response = $this->getJson('/api/support/tickets?page=1&pageSize=2&sort=updated_desc');

        $response
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('pageSize', 2)
            ->assertJsonCount(2, 'items')
            ->assertJsonStructure([
                'items' => [[
                    'id',
                    'subject',
                    'submeta',
                    'customer',
                    'priority',
                    'status',
                    'agent',
                    'updated',
                    'requester',
                    'team',
                    'source',
                    'category',
                    'isAssignedToMe',
                    'isWaitingOnCustomer',
                    'isForwarded',
                    'forwardMode',
                    'forwardTarget',
                    'hasLinkedTask',
                    'linkedTaskCount',
                    'hasAttachments',
                    'attachmentCount',
                    'waitingOn',
                ]],
                'total',
                'page',
                'pageSize',
            ]);
    }

    public function test_it_returns_support_reference_data(): void
    {
        $response = $this->getJson('/api/support/reference-data');

        $response
            ->assertOk()
            ->assertJsonPath('teams.0.id', 'billing-support')
            ->assertJsonPath('categories.0.isActive', true)
            ->assertJsonPath('agents.0.name', 'Amit')
            ->assertJsonPath('queues.0.id', 'all-open')
            ->assertJsonPath('priorities.0', 'Low')
            ->assertJsonPath('statuses.0', 'Open')
            ->assertJsonStructure([
                'teams' => [['id', 'name', 'isActive']],
                'categories' => [['id', 'name', 'isActive']],
                'agents' => [['id', 'name', 'email', 'isActive']],
                'queues' => [['id', 'name', 'isActive']],
                'priorities',
                'statuses',
                'sources',
            ]);
    }

    public function test_it_returns_separate_reference_lists(): void
    {
        $this->getJson('/api/support/teams')
            ->assertOk()
            ->assertJsonFragment(['id' => 'portal-support', 'name' => 'Portal Support', 'isActive' => true]);

        $this->getJson('/api/support/categories')
            ->assertOk()
            ->assertJsonFragment(['id' => 'fc', 'name' => 'FC', 'isActive' => true]);

        $this->getJson('/api/support/agents')
            ->assertOk()
            ->assertJsonFragment(['id' => 'amit', 'name' => 'Amit', 'email' => 'amit@example.com', 'isActive' => true]);
    }

    public function test_it_filters_tickets(): void
    {
        $response = $this->getJson('/api/support/tickets?search=claim&queue=Portal%20Support&priority=High&status=Open');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', 'TK-1048');
    }

    public function test_it_returns_ticket_detail(): void
    {
        $response = $this->getJson('/api/support/tickets/TK-1048');

        $response
            ->assertOk()
            ->assertJsonPath('id', 'TK-1048')
            ->assertJsonStructure([
                'id',
                'subject',
                'slaFirstResponse',
                'slaResolution',
                'activities' => [['id', 'title', 'time', 'type', 'body', 'authorId', 'authorName', 'visibility', 'isInternal', 'relatedEntityId', 'parentActivityId', 'mentions']],
                'relatedItems' => [['id', 'title', 'meta']],
                'forwardState',
                'linkedTaskSummary',
                'attachmentSummary',
                'linkedTasks',
                'attachments' => [['id', 'fileName', 'size', 'uploadedBy', 'uploadedAt', 'visibility']],
            ]);
    }

    public function test_it_returns_linked_tasks_for_ticket(): void
    {
        $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'task',
            'taskTitle' => 'Review attachment workflow',
            'taskAssignee' => 'Amit',
        ])->assertOk();

        $response = $this->getJson('/api/support/tickets/TK-1048/linked-tasks');

        $response
            ->assertOk()
            ->assertJsonPath('0.id', 'TASK-2001')
            ->assertJsonPath('0.status', 'Open')
            ->assertJsonStructure([['id', 'title', 'assignee', 'status', 'createdAt', 'updatedAt']]);
    }

    public function test_it_creates_ticket_with_attachment_references(): void
    {
        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'New portal error',
            'customer' => 'HLIB',
            'requester' => 'Nur Aisyah',
            'team' => 'Portal Support',
            'category' => 'Portal / Error',
            'priority' => 'High',
            'message' => 'Please check this issue.',
            'attachmentIds' => ['ATT-2001'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['ticketId']);

        $detail = $this->getJson('/api/support/tickets/'.$response->json('ticketId'));

        $detail
            ->assertOk()
            ->assertJsonPath('attachmentSummary.count', 1)
            ->assertJsonPath('attachments.0.id', 'ATT-2001');
    }

    public function test_it_returns_global_attachment_lookup(): void
    {
        $response = $this->getJson('/api/support/attachments?search=claim&visibility=public&page=1&pageSize=10');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('items.0.ticketId', 'TK-1048')
            ->assertJsonPath('items.0.ticketSubject', 'Unable to submit claim after document upload')
            ->assertJsonStructure([
                'items' => [['id', 'fileName', 'size', 'uploadedBy', 'uploadedAt', 'visibility', 'ticketId', 'ticketSubject']],
                'total',
                'page',
                'pageSize',
            ]);
    }

    public function test_it_uploads_attachment_without_ticket_context(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/support/attachments/upload', [
            'file' => UploadedFile::fake()->create('error-screenshot.png', 512, 'image/png'),
            'visibility' => 'public',
            'customer' => 'HLIB',
            'requester' => 'Amit',
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('id', 'ATT-2001')
            ->assertJsonPath('fileName', 'error-screenshot.png')
            ->assertJsonPath('uploadedBy', 'Amit')
            ->assertJsonPath('visibility', 'public')
            ->assertJsonPath('ticketId', null)
            ->assertJsonPath('ticketSubject', null)
            ->assertJsonStructure(['id', 'fileName', 'size', 'uploadedBy', 'uploadedAt', 'visibility', 'ticketId', 'ticketSubject']);

        Storage::disk('local')->assertExists('support-attachments/ATT-2001-error-screenshot.png');
    }

    public function test_it_uploads_attachment_to_ticket_and_logs_activity(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/support/attachments/upload', [
            'file' => UploadedFile::fake()->create('claim-form.pdf', 128, 'application/pdf'),
            'visibility' => 'internal',
            'ticketId' => 'TK-1048',
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('id', 'ATT-2001')
            ->assertJsonPath('ticketId', 'TK-1048')
            ->assertJsonPath('ticketSubject', 'Unable to submit claim after document upload')
            ->assertJsonPath('visibility', 'internal');

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('activities.0.type', 'attachment-added')
            ->assertJsonPath('activities.0.relatedEntityId', 'ATT-2001')
            ->assertJsonPath('attachments.0.id', 'ATT-2001');
    }

    public function test_upload_attachment_validates_file_type(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/support/attachments/upload', [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            'visibility' => 'public',
        ], ['Accept' => 'application/json']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_it_filters_attachment_lookup_by_ticket(): void
    {
        $response = $this->getJson('/api/support/attachments?ticketId=TK-1048&page=1&pageSize=10');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('items.0.ticketId', 'TK-1048');
    }

    public function test_it_returns_ticket_scoped_attachments(): void
    {
        $response = $this->getJson('/api/support/tickets/TK-1048/attachments');

        $response
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('items.0.ticketId', 'TK-1048');
    }

    public function test_ticket_scoped_attachments_returns_not_found_for_missing_ticket(): void
    {
        $response = $this->getJson('/api/support/tickets/TK-9999/attachments');

        $response
            ->assertNotFound()
            ->assertJson(['message' => 'Ticket not found.']);
    }

    public function test_bulk_status_validates_status_enum(): void
    {
        $response = $this->postJson('/api/support/tickets/bulk/status', [
            'ticketIds' => ['TK-1048'],
            'status' => 'Almost Done',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_bulk_priority_updates_known_tickets(): void
    {
        $response = $this->postJson('/api/support/tickets/bulk/priority', [
            'ticketIds' => ['TK-1048', 'TK-1047', 'TK-9999'],
            'priority' => 'High',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updatedCount' => 2,
            ]);
    }

    public function test_reply_creates_activity_id(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/reply', [
            'message' => 'Thanks, we are checking this now.',
            'isInternalNote' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['activityId']);

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('activities.0.body', 'Thanks, we are checking this now.')
            ->assertJsonPath('activities.0.authorName', 'Amit')
            ->assertJsonPath('activities.0.visibility', 'public')
            ->assertJsonPath('activities.0.isInternal', false);
    }

    public function test_reply_supports_parent_activity_and_structured_mentions(): void
    {
        $user = User::query()->create([
            'name' => 'Arka',
            'email' => 'arka@example.com',
            'password' => 'password',
        ]);
        $token = $this->postJson('/api/auth/login', [
            'email' => 'arka@example.com',
            'password' => 'password',
        ])->json('token');

        $response = $this->postJson('/api/support/tickets/TK-1048/reply', [
            'message' => 'Hi @arka please verify with @portal-support.',
            'isInternalNote' => false,
            'parentActivityId' => 'ACT-9001',
            'mentions' => [
                ['id' => 'user_44', 'kind' => 'user', 'display' => 'arka'],
                ['id' => 'portal-support', 'kind' => 'team', 'display' => 'Portal Support'],
            ],
        ], ['Authorization' => 'Bearer '.$token]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['activityId']);

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('activities.0.id', $response->json('activityId'))
            ->assertJsonPath('activities.0.authorId', (string) $user->id)
            ->assertJsonPath('activities.0.authorName', 'Arka')
            ->assertJsonPath('activities.0.parentActivityId', 'ACT-9001')
            ->assertJsonPath('activities.0.mentions.0.id', 'user_44')
            ->assertJsonPath('activities.0.mentions.0.display', 'arka')
            ->assertJsonPath('activities.0.mentions.0.kind', 'user')
            ->assertJsonPath('activities.0.mentions.1.id', 'portal-support')
            ->assertJsonPath('activities.0.mentions.1.kind', 'team');
    }

    public function test_forward_creates_internal_activity(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'external',
            'to' => 'recipient@example.com',
            'comment' => 'Please review the attachment workflow.',
            'includeAttachments' => true,
            'attachmentIds' => ['ATT-1001', 'ATT-1002'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('linkedTaskId', null)
            ->assertJsonPath('message', 'Ticket forwarded successfully')
            ->assertJsonStructure(['forwardId', 'linkedTaskId', 'message']);

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('activities.0.title', 'Ticket forwarded')
            ->assertJsonPath('activities.0.type', 'forward')
            ->assertJsonPath('activities.0.body', 'Forwarded to recipient@example.com with note: Please review the attachment workflow. Attachments included: ATT-1001, ATT-1002.')
            ->assertJsonPath('activities.0.authorName', 'Amit')
            ->assertJsonPath('activities.0.visibility', 'internal')
            ->assertJsonPath('activities.0.isInternal', true);

        $list = $this->getJson('/api/support/tickets?search=TK-1048');

        $list
            ->assertOk()
            ->assertJsonPath('items.0.isForwarded', true)
            ->assertJsonPath('items.0.forwardMode', 'external')
            ->assertJsonPath('items.0.forwardTarget', 'recipient@example.com')
            ->assertJsonPath('items.0.hasAttachments', true)
            ->assertJsonPath('items.0.waitingOn', 'external');
    }

    public function test_forward_validates_email(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'external',
            'to' => 'not-an-email',
            'note' => 'Please review.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_forward_returns_not_found_for_missing_ticket(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-9999/forward', [
            'mode' => 'external',
            'to' => 'recipient@example.com',
            'note' => 'Please review.',
        ]);

        $response
            ->assertNotFound()
            ->assertJson(['message' => 'Ticket not found.']);
    }

    public function test_forward_to_team_updates_queue_and_logs_activity(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'team',
            'teamId' => 'portal-support',
            'comment' => 'Please take over this case.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('linkedTaskId', null);

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('team', 'Portal Support')
            ->assertJsonPath('activities.0.title', 'Ticket forwarded to team')
            ->assertJsonPath('activities.0.type', 'forward')
            ->assertJsonPath('activities.0.body', 'Forwarded to Portal Support with internal comment: Please take over this case.')
            ->assertJsonPath('activities.0.visibility', 'internal')
            ->assertJsonPath('activities.0.isInternal', true);
    }

    public function test_forward_to_team_requires_team_id(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'team',
            'comment' => 'Please take over this case.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teamId');
    }

    public function test_forward_as_linked_task_creates_task_reference_and_logs_activity(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'task',
            'taskTitle' => 'Review attachment workflow',
            'taskAssignee' => 'Amit',
            'comment' => 'Please review this flow.',
            'attachmentIds' => ['ATT-1001'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('linkedTaskId', 'TASK-2001')
            ->assertJsonPath('message', 'Ticket forwarded successfully');

        $detail = $this->getJson('/api/support/tickets/TK-1048');

        $detail
            ->assertOk()
            ->assertJsonPath('activities.0.title', 'Linked task created')
            ->assertJsonPath('activities.0.type', 'linked-task-created')
            ->assertJsonPath('activities.0.body', 'Created linked task TASK-2001 assigned to Amit. Comment: Please review this flow. Attachments included: ATT-1001.')
            ->assertJsonPath('activities.0.relatedEntityId', 'TASK-2001')
            ->assertJsonPath('activities.0.visibility', 'internal')
            ->assertJsonPath('activities.0.isInternal', true);

        $detail
            ->assertJsonPath('linkedTaskSummary.count', 1)
            ->assertJsonPath('linkedTaskSummary.openCount', 1)
            ->assertJsonPath('linkedTasks.0.id', 'TASK-2001');
    }

    public function test_forward_as_linked_task_requires_task_title(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'task',
            'taskAssignee' => 'Amit',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('taskTitle');
    }

    public function test_forward_validates_attachment_ids(): void
    {
        $response = $this->postJson('/api/support/tickets/TK-1048/forward', [
            'mode' => 'external',
            'to' => 'recipient@example.com',
            'attachmentIds' => ['ATT-1001', 'ATT-1001'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachmentIds.1');
    }
}
