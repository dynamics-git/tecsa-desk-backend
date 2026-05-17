<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportSuperAdminSeeder extends Seeder
{
    /**
     * Promote Amit to super-admin in all relevant access-control stores.
     */
    public function run(): void
    {
        $email = 'amit@example.com';

        $ticketPermissions = [
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

        $workspacePermissions = [
            'support.setup.view',
            'support.permissions.view',
            'support.permissions.manage',
            'support.queues.view',
            'support.customers.view',
            'support.automation.view',
            'support.reports.view',
        ];

        $allPermissions = array_values(array_unique(array_merge($ticketPermissions, $workspacePermissions)));

        $user = DB::table('users')->where('email', $email)->first();

        if (! $user) {
            throw new \RuntimeException("User not found: {$email}");
        }

        $updates = [];

        if (Schema::hasColumn('users', 'role')) {
            $updates['role'] = 'super-admin';
        }

        if (Schema::hasColumn('users', 'user_type')) {
            $updates['user_type'] = 'Internal';
        }

        if (Schema::hasColumn('users', 'ticket_visibility')) {
            $updates['ticket_visibility'] = 'All';
        }

        if (Schema::hasColumn('users', 'is_admin')) {
            $updates['is_admin'] = 1;
        }

        if (Schema::hasColumn('users', 'permissions')) {
            $updates['permissions'] = json_encode($allPermissions);
        }

        if (Schema::hasColumn('users', 'team_ids')) {
            $updates['team_ids'] = json_encode([]);
        }

        if (Schema::hasColumn('users', 'queue_ids')) {
            $updates['queue_ids'] = json_encode([]);
        }

        if (Schema::hasColumn('users', 'customer_ids')) {
            $updates['customer_ids'] = json_encode([]);
        }

        if ($updates !== []) {
            DB::table('users')->where('id', $user->id)->update($updates);
        }

        if (Schema::hasTable('support_permission_roles')) {
            DB::table('support_permission_roles')->updateOrInsert(
                ['user_email' => $email],
                [
                    'user_id' => $user->id,
                    'user_type' => 'Internal',
                    'role' => 'super-admin',
                    'permissions' => json_encode($allPermissions),
                    'ticket_visibility' => 'All',
                    'is_admin' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('support_user_scopes')) {
            DB::table('support_user_scopes')->updateOrInsert(
                ['user_email' => $email],
                [
                    'user_id' => $user->id,
                    'team_ids' => json_encode([]),
                    'queue_ids' => json_encode([]),
                    'customer_ids' => json_encode([]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('permission_roles')) {
            DB::table('permission_roles')->updateOrInsert(
                ['id' => 'super-admin'],
                [
                    'name' => 'Super Admin',
                    'user_type' => 'Internal',
                    'ticket_visibility' => 'All',
                    'permissions' => json_encode($allPermissions),
                    'team_ids' => json_encode([]),
                    'customer_ids' => json_encode([]),
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('user_permission_roles')) {
            DB::table('user_permission_roles')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => 'super-admin'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
