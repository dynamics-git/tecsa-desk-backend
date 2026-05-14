<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketRelatedItem;
use Illuminate\Database\Seeder;

class SupportTicketSeeder extends Seeder
{
    /**
     * Seed support ticket sample data for frontend integration and local testing.
     */
    public function run(): void
    {
        foreach ($this->tickets() as $ticket) {
            SupportTicket::query()->updateOrCreate(
                ['id' => $ticket['id']],
                [
                    'subject' => $ticket['subject'],
                    'submeta' => $ticket['submeta'],
                    'customer' => $ticket['customer'],
                    'priority' => $ticket['priority'],
                    'status' => $ticket['status'],
                    'agent' => $ticket['agent'],
                    'requester' => $ticket['requester'],
                    'team' => $ticket['team'],
                    'source' => $ticket['source'],
                    'category' => $ticket['category'],
                    'is_assigned_to_me' => $ticket['isAssignedToMe'],
                    'is_waiting_on_customer' => $ticket['isWaitingOnCustomer'],
                    'sla_first_response_at' => $ticket['slaFirstResponse'],
                    'sla_resolution_at' => $ticket['slaResolution'],
                    'created_at' => $ticket['updated'],
                    'updated_at' => $ticket['updated'],
                ],
            );

            foreach ($ticket['activities'] as $activity) {
                SupportTicketActivity::query()->updateOrCreate(
                    ['id' => $activity['id']],
                    [
                        'support_ticket_id' => $ticket['id'],
                        'title' => $activity['title'],
                        'type' => $activity['type'],
                        'message' => $activity['message'] ?? null,
                        'author_name' => $activity['authorName'] ?? 'System',
                        'visibility' => $activity['visibility'] ?? 'public',
                        'is_internal' => $activity['isInternal'] ?? false,
                        'related_entity_id' => $activity['relatedEntityId'] ?? null,
                        'occurred_at' => $activity['time'],
                    ],
                );
            }

            foreach ($ticket['attachments'] ?? [] as $attachment) {
                SupportTicketAttachment::query()->updateOrCreate(
                    ['id' => $attachment['id']],
                    [
                        'support_ticket_id' => $ticket['id'],
                        'file_name' => $attachment['fileName'],
                        'size' => $attachment['size'],
                        'uploaded_by' => $attachment['uploadedBy'],
                        'uploaded_at' => $attachment['uploadedAt'],
                        'visibility' => $attachment['visibility'],
                    ],
                );
            }

            foreach ($ticket['relatedItems'] as $relatedItem) {
                SupportTicketRelatedItem::query()->updateOrCreate(
                    [
                        'support_ticket_id' => $ticket['id'],
                        'related_id' => $relatedItem['id'],
                    ],
                    [
                        'title' => $relatedItem['title'],
                        'meta' => $relatedItem['meta'],
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tickets(): array
    {
        return [
            [
                'id' => 'TK-1048',
                'subject' => 'Unable to submit claim after document upload',
                'submeta' => 'Portal / Attachment / Error',
                'customer' => 'HLIB',
                'priority' => 'High',
                'status' => 'Open',
                'agent' => 'Amit',
                'updated' => '2026-05-02T10:25:00Z',
                'requester' => 'Nur Aisyah',
                'team' => 'Portal Support',
                'source' => 'Customer Portal',
                'category' => 'Claims / Attachments',
                'isAssignedToMe' => true,
                'isWaitingOnCustomer' => false,
                'slaFirstResponse' => '2026-05-02T11:00:00Z',
                'slaResolution' => '2026-05-02T17:30:00Z',
                'activities' => [
                    ['id' => 'ACT-9001', 'title' => 'Amit viewed the ticket', 'time' => '2026-05-02T10:25:00Z', 'type' => 'view'],
                    ['id' => 'ACT-9002', 'title' => 'Attachment added', 'time' => '2026-05-02T09:58:00Z', 'type' => 'attachment-added', 'message' => 'Attachment ATT-1001 added.', 'relatedEntityId' => 'ATT-1001'],
                ],
                'attachments' => [
                    ['id' => 'ATT-1001', 'fileName' => 'claim-form.pdf', 'size' => 245120, 'uploadedBy' => 'Nur Aisyah', 'uploadedAt' => '2026-05-02T09:58:00Z', 'visibility' => 'public'],
                    ['id' => 'ATT-1002', 'fileName' => 'upload-error.png', 'size' => 48120, 'uploadedBy' => 'Nur Aisyah', 'uploadedAt' => '2026-05-02T09:59:00Z', 'visibility' => 'public'],
                ],
                'relatedItems' => [
                    ['id' => 'TK-1029', 'title' => 'Claims upload issue', 'meta' => 'Resolved last week'],
                ],
            ],
            [
                'id' => 'TK-1047',
                'subject' => 'Customer portal password reset email not received',
                'submeta' => 'Portal / Authentication',
                'customer' => 'Acme Health',
                'priority' => 'Medium',
                'status' => 'In Progress',
                'agent' => 'Priya',
                'updated' => '2026-05-02T09:40:00Z',
                'requester' => 'Jason Lee',
                'team' => 'Portal Support',
                'source' => 'Email',
                'category' => 'Access / Password Reset',
                'isAssignedToMe' => false,
                'isWaitingOnCustomer' => false,
                'slaFirstResponse' => '2026-05-02T10:15:00Z',
                'slaResolution' => '2026-05-03T09:30:00Z',
                'activities' => [
                    ['id' => 'ACT-9003', 'title' => 'Priya started investigation', 'time' => '2026-05-02T09:40:00Z', 'type' => 'status-change'],
                ],
                'relatedItems' => [
                    ['id' => 'KB-220', 'title' => 'Password reset delivery checks', 'meta' => 'Knowledge base'],
                ],
            ],
            [
                'id' => 'TK-1046',
                'subject' => 'Missing invoice in billing dashboard',
                'submeta' => 'Billing / Invoice',
                'customer' => 'Globex',
                'priority' => 'Low',
                'status' => 'Pending Customer',
                'agent' => 'Amit',
                'updated' => '2026-05-01T16:05:00Z',
                'requester' => 'Maria Gomez',
                'team' => 'Billing Support',
                'source' => 'Customer Portal',
                'category' => 'Billing / Documents',
                'isAssignedToMe' => true,
                'isWaitingOnCustomer' => true,
                'slaFirstResponse' => '2026-05-01T17:00:00Z',
                'slaResolution' => '2026-05-04T17:00:00Z',
                'activities' => [
                    ['id' => 'ACT-9004', 'title' => 'Amit requested billing period details', 'time' => '2026-05-01T16:05:00Z', 'type' => 'reply'],
                ],
                'relatedItems' => [],
            ],
            [
                'id' => 'TK-1045',
                'subject' => 'Urgent outage report for API callbacks',
                'submeta' => 'API / Webhook / Outage',
                'customer' => 'Northwind',
                'priority' => 'Urgent',
                'status' => 'Open',
                'agent' => 'Mei Lin',
                'updated' => '2026-05-02T10:10:00Z',
                'requester' => 'Daniel Tan',
                'team' => 'Integration Support',
                'source' => 'Phone',
                'category' => 'API / Callbacks',
                'isAssignedToMe' => false,
                'isWaitingOnCustomer' => false,
                'slaFirstResponse' => '2026-05-02T10:20:00Z',
                'slaResolution' => '2026-05-02T13:00:00Z',
                'activities' => [
                    ['id' => 'ACT-9005', 'title' => 'Outage bridge created', 'time' => '2026-05-02T10:10:00Z', 'type' => 'incident'],
                ],
                'relatedItems' => [
                    ['id' => 'INC-88', 'title' => 'Callback latency incident', 'meta' => 'Active incident'],
                ],
            ],
        ];
    }
}
