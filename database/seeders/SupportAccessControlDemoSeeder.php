<?php

namespace Database\Seeders;

use App\Models\CustomerUserAccess;
use App\Models\SupportPermissionRole;
use App\Models\SupportUserScope;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportAccessControlDemoSeeder extends Seeder
{
    /**
     * Seed enterprise-style access control demo data used for permission and scope testing.
     */
    public function run(): void
    {
        $usersByEmail = User::query()
            ->whereIn('email', array_column($this->profiles(), 'email'))
            ->get(['id', 'email'])
            ->keyBy('email');

        foreach ($this->profiles() as $profile) {
            $user = $usersByEmail->get($profile['email']);

            SupportPermissionRole::query()->updateOrCreate(
                ['user_email' => $profile['email']],
                [
                    'user_id' => $user?->id,
                    'user_type' => $profile['userType'],
                    'role' => $profile['role'],
                    'permissions' => $profile['permissions'],
                    'ticket_visibility' => $profile['ticketVisibility'],
                    'is_admin' => $profile['isAdmin'],
                ],
            );

            SupportUserScope::query()->updateOrCreate(
                ['user_email' => $profile['email']],
                [
                    'user_id' => $user?->id,
                    'team_ids' => $profile['teamIds'],
                    'queue_ids' => $profile['queueIds'],
                    'customer_ids' => $profile['customerIds'],
                ],
            );
        }

        foreach ($this->customerAccessRows() as $row) {
            $user = $usersByEmail->get($row['user_email']);

            CustomerUserAccess::query()->updateOrCreate(
                [
                    'user_email' => $row['user_email'],
                    'customer_id' => $row['customer_id'],
                ],
                [
                    'user_id' => $user?->id,
                    'access_level' => $row['access_level'],
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function profiles(): array
    {
        $allTicketPermissions = [
            'support.ticket.view',
            'support.ticket.reply',
            'support.ticket.internalNote',
            'support.ticket.assign',
            'support.ticket.changeStatus',
            'support.ticket.changePriority',
            'support.ticket.forward',
            'support.ticket.uploadAttachment',
            'support.ticket.bulkUpdate',
        ];

        return [
            [
                'email' => 'amit@example.com',
                'userType' => 'Internal',
                'role' => 'SupportAdmin',
                'permissions' => $allTicketPermissions,
                'ticketVisibility' => 'All',
                'isAdmin' => true,
                'teamIds' => ['portal-support', 'billing-support', 'integration-support'],
                'queueIds' => ['all-open', 'assigned-to-me', 'waiting-on-customer', 'forwarded', 'portal-support'],
                'customerIds' => ['hlib', 'acme-health', 'globex', 'northwind'],
            ],
            [
                'email' => 'priya@example.com',
                'userType' => 'Internal',
                'role' => 'L1Agent',
                'permissions' => [
                    'support.ticket.view',
                    'support.ticket.reply',
                    'support.ticket.assign',
                    'support.ticket.changeStatus',
                    'support.ticket.uploadAttachment',
                ],
                'ticketVisibility' => 'Team',
                'isAdmin' => false,
                'teamIds' => ['portal-support'],
                'queueIds' => ['portal-support', 'assigned-to-me'],
                'customerIds' => ['hlib', 'acme-health'],
            ],
            [
                'email' => 'mei.lin@example.com',
                'userType' => 'Internal',
                'role' => 'EscalationLead',
                'permissions' => [
                    'support.ticket.view',
                    'support.ticket.reply',
                    'support.ticket.internalNote',
                    'support.ticket.assign',
                    'support.ticket.changeStatus',
                    'support.ticket.changePriority',
                    'support.ticket.forward',
                    'support.ticket.uploadAttachment',
                ],
                'ticketVisibility' => 'TeamAndAssigned',
                'isAdmin' => false,
                'teamIds' => ['integration-support'],
                'queueIds' => ['forwarded'],
                'customerIds' => ['northwind', 'globex'],
            ],
            [
                'email' => 'sarah@example.com',
                'userType' => 'Customer',
                'role' => 'CustomerManager',
                'permissions' => [
                    'support.ticket.view',
                    'support.ticket.reply',
                    'support.ticket.uploadAttachment',
                ],
                'ticketVisibility' => 'Customer',
                'isAdmin' => false,
                'teamIds' => [],
                'queueIds' => [],
                'customerIds' => ['hlib'],
            ],
            [
                'email' => 'jason.lee@example.com',
                'userType' => 'Customer',
                'role' => 'CustomerUser',
                'permissions' => [
                    'support.ticket.view',
                    'support.ticket.reply',
                    'support.ticket.uploadAttachment',
                ],
                'ticketVisibility' => 'Own',
                'isAdmin' => false,
                'teamIds' => [],
                'queueIds' => [],
                'customerIds' => ['acme-health'],
            ],
        ];
    }

    /**
     * @return array<int, array{user_email: string, customer_id: string, access_level: string}>
     */
    private function customerAccessRows(): array
    {
        return [
            ['user_email' => 'sarah@example.com', 'customer_id' => 'hlib', 'access_level' => 'CompanyTickets'],
            ['user_email' => 'jason.lee@example.com', 'customer_id' => 'acme-health', 'access_level' => 'OwnTickets'],
        ];
    }
}