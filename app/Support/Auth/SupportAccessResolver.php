<?php

namespace App\Support\Auth;

use App\Models\CustomerUserAccess;
use App\Models\SupportCustomer;
use App\Models\SupportPermissionRole;
use App\Models\SupportQueue;
use App\Models\SupportTeam;
use App\Models\SupportTicket;
use App\Models\SupportUserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class SupportAccessResolver
{
    private const USER_TYPES = ['Internal', 'Customer'];

    private const VISIBILITY_MODES = ['All', 'Team', 'Assigned', 'TeamAndAssigned', 'Customer', 'Own'];

    private const ACCESS_LEVELS = ['OwnTickets', 'CompanyTickets', 'Admin'];

    private const DEFAULT_TICKET_PERMISSIONS = [
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

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $contextCache = [];

    /**
     * @return array<string, mixed>
     */
    public function resolve(CurrentUser $currentUser): array
    {
        $cacheKey = strtolower($currentUser->id.'|'.($currentUser->email ?? ''));

        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        if (! $this->accessSchemaReady()) {
            return $this->contextCache[$cacheKey] = $this->defaultContext($currentUser);
        }

        $role = SupportPermissionRole::query()
            ->where('is_active', true)
            ->where($this->matchCurrentUser($currentUser))
            ->first();

        $scope = SupportUserScope::query()
            ->where('is_active', true)
            ->where($this->matchCurrentUser($currentUser))
            ->first();

        $customerAccessRows = CustomerUserAccess::query()
            ->where($this->matchCurrentUser($currentUser))
            ->where('is_active', true)
            ->get(['customer_id', 'customer_name', 'access_level', 'can_create_ticket', 'can_view_attachments', 'can_reply', 'is_active']);

        $rolePermissions = $this->normalizeStringArray($role?->permissions ?? []);
        $customerAccessEntries = $customerAccessRows
            ->map(function (CustomerUserAccess $row): array {
                return [
                    'customerId' => (string) ($row->customer_id ?? ''),
                    'customerName' => $row->customer_name !== null && $row->customer_name !== ''
                        ? (string) $row->customer_name
                        : (string) ($row->customer_id ?? ''),
                    'accessLevel' => (string) ($row->access_level ?? ''),
                    'canCreateTicket' => (bool) ($row->can_create_ticket ?? false),
                    'canViewAttachments' => (bool) ($row->can_view_attachments ?? false),
                    'canReply' => (bool) ($row->can_reply ?? false),
                    'isActive' => (bool) ($row->is_active ?? true),
                ];
            })
            ->filter(fn (array $entry): bool => in_array((string) ($entry['accessLevel'] ?? ''), self::ACCESS_LEVELS, true))
            ->values()
            ->all();

        $customerAccessLevels = collect($customerAccessEntries)
            ->pluck('accessLevel')
            ->map(fn ($level): string => (string) $level)
            ->unique()
            ->values()
            ->all();

        $accessCustomerIds = collect($customerAccessEntries)
            ->pluck('customerId')
            ->map(fn ($id): string => (string) $id)
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
        $scopeCustomerIds = $this->normalizeStringArray($scope?->customer_ids ?? []);

        $context = [
            'userType' => $this->normalizeEnum(
                $role?->user_type,
                self::USER_TYPES,
                $customerAccessLevels !== [] ? 'Customer' : 'Internal',
            ),
            'role' => $role?->role ?: ($customerAccessLevels !== [] ? 'Customer' : 'Agent'),
            'permissions' => $this->toContractPermissions($rolePermissions !== [] ? $rolePermissions : self::DEFAULT_TICKET_PERMISSIONS),
            'teamIds' => $this->normalizeStringArray($scope?->team_ids ?? []),
            'queueIds' => $this->normalizeStringArray($scope?->queue_ids ?? []),
            'customerIds' => array_values(array_unique([...$scopeCustomerIds, ...$accessCustomerIds])),
            'accessCustomerIds' => $accessCustomerIds,
            'ticketVisibility' => $this->normalizeEnum(
                $role?->ticket_visibility,
                self::VISIBILITY_MODES,
                $customerAccessLevels !== [] ? 'Customer' : 'Own',
            ),
            'customerAccess' => $customerAccessEntries,
            'customerAccessLevels' => $customerAccessLevels,
            'isAdmin' => (bool) ($role?->is_admin ?? false) || in_array('Admin', $customerAccessLevels, true),
        ];

        return $this->contextCache[$cacheKey] = $context;
    }

    /**
     * @return array<string, mixed>
     */
    public function authPayload(CurrentUser $currentUser): array
    {
        $context = $this->resolve($currentUser);

        return [
            'id' => $currentUser->id,
            'name' => $currentUser->name,
            'email' => $currentUser->email,
            'userType' => $context['userType'],
            'role' => $context['role'],
            'permissions' => $context['permissions'],
            'teamIds' => $context['teamIds'],
            'queueIds' => $context['queueIds'],
            'customerIds' => $context['customerIds'],
            'ticketVisibility' => $context['ticketVisibility'],
            'customerAccess' => $context['customerAccess'],
            'isAdmin' => $context['isAdmin'],
        ];
    }

    public function canViewTicket(CurrentUser $currentUser, SupportTicket $ticket): bool
    {
        $context = $this->resolve($currentUser);

        if ($context['isAdmin'] || $context['ticketVisibility'] === 'All') {
            return true;
        }

        $allowedTeamNames = $this->teamNamesFromContext($context);
        $allowedCustomerNames = $this->customerNamesFromContext($context);

        $byVisibility = match ($context['ticketVisibility']) {
            'Team' => in_array($ticket->team, $allowedTeamNames, true),
            'Assigned' => $this->isOwnedByCurrentUser($currentUser, $ticket),
            'TeamAndAssigned' => in_array($ticket->team, $allowedTeamNames, true) || $this->isOwnedByCurrentUser($currentUser, $ticket),
            'Customer' => in_array($ticket->customer, $allowedCustomerNames, true),
            'Own' => $this->isOwnedByCurrentUser($currentUser, $ticket),
            default => false,
        };

        if (! $byVisibility) {
            return false;
        }

        if (($context['userType'] ?? 'Internal') === 'Customer') {
            return $this->customerAccessAllowsTicket($context, $currentUser, $ticket);
        }

        return true;
    }

    public function hasPermission(CurrentUser $currentUser, string $permission): bool
    {
        $context = $this->resolve($currentUser);

        if ($context['isAdmin']) {
            return true;
        }

        $expected = $this->normalizePermission($permission);

        return collect($context['permissions'])
            ->contains(fn ($value): bool => $this->normalizePermission((string) $value) === $expected);
    }

    public function canCreateTicket(CurrentUser $currentUser, ?string $customer = null): bool
    {
        $context = $this->resolve($currentUser);

        if ($context['isAdmin']) {
            return true;
        }

        if (($context['userType'] ?? 'Internal') !== 'Customer') {
            return true;
        }

        $allowedEntries = collect($context['customerAccess'] ?? [])
            ->filter(fn ($entry): bool => is_array($entry))
            ->filter(fn (array $entry): bool => (bool) ($entry['isActive'] ?? false))
            ->filter(fn (array $entry): bool => (bool) ($entry['canCreateTicket'] ?? false));

        if ($allowedEntries->isEmpty()) {
            return false;
        }

        if ($customer === null || trim($customer) === '') {
            return true;
        }

        $needle = strtolower(trim($customer));

        return $allowedEntries->contains(function (array $entry) use ($needle): bool {
            $customerId = strtolower(trim((string) ($entry['customerId'] ?? '')));
            $customerName = strtolower(trim((string) ($entry['customerName'] ?? '')));

            return $needle !== '' && ($needle === $customerId || $needle === $customerName);
        });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    public function applyTicketScope(Builder $query, CurrentUser $currentUser): void
    {
        $context = $this->resolve($currentUser);

        if ($context['isAdmin'] || $context['ticketVisibility'] === 'All') {
            return;
        }

        $allowedTeamNames = $this->teamNamesFromContext($context);
        $allowedCustomerNames = $this->customerNamesFromContext($context);

        match ($context['ticketVisibility']) {
            'Team' => $query->whereIn('team', $allowedTeamNames ?: ['__no_team_scope__']),
            'Assigned' => $query->where('agent', $currentUser->name),
            'TeamAndAssigned' => $query->where(function (Builder $builder) use ($allowedTeamNames, $currentUser): void {
                $builder
                    ->whereIn('team', $allowedTeamNames ?: ['__no_team_scope__'])
                    ->orWhere('agent', $currentUser->name);
            }),
            'Customer' => $query->whereIn('customer', $allowedCustomerNames ?: ['__no_customer_scope__']),
            'Own' => $this->applyOwnScope($query, $currentUser),
            default => $query->whereRaw('1 = 0'),
        };

        if (($context['userType'] ?? 'Internal') === 'Customer') {
            $this->applyCustomerAccessScope($query, $context, $currentUser);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function teamNamesFromContext(array $context): array
    {
        $teamIds = $this->normalizeStringArray($context['teamIds'] ?? []);
        $queueIds = $this->normalizeStringArray($context['queueIds'] ?? []);

        $teamNames = [];

        if ($teamIds !== [] && Schema::hasTable('support_teams')) {
            $teamNames = array_merge(
                $teamNames,
                SupportTeam::query()->whereIn('id', $teamIds)->pluck('name')->map(fn ($name): string => (string) $name)->all(),
            );
        }

        if ($queueIds !== [] && Schema::hasTable('support_queues')) {
            $queues = SupportQueue::query()->whereIn('id', $queueIds)->get(['name', 'team_id']);
            $teamNames = array_merge(
                $teamNames,
                $queues->pluck('name')->map(fn ($name): string => (string) $name)->all(),
            );

            $queueTeamIds = $queues
                ->pluck('team_id')
                ->filter(fn ($id): bool => is_string($id) && $id !== '')
                ->values()
                ->all();

            if ($queueTeamIds !== [] && Schema::hasTable('support_teams')) {
                $teamNames = array_merge(
                    $teamNames,
                    SupportTeam::query()->whereIn('id', $queueTeamIds)->pluck('name')->map(fn ($name): string => (string) $name)->all(),
                );
            }
        }

        return array_values(array_unique(array_filter($teamNames, fn ($value): bool => is_string($value) && $value !== '')));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function customerNamesFromContext(array $context): array
    {
        $customerIds = $this->normalizeStringArray($context['customerIds'] ?? []);
        $customerIds = array_values(array_unique([...$customerIds, ...$this->normalizeStringArray($context['accessCustomerIds'] ?? [])]));

        if ($customerIds === [] || ! Schema::hasTable('support_customers')) {
            return [];
        }

        return SupportCustomer::query()
            ->whereIn('id', $customerIds)
            ->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function isOwnedByCurrentUser(CurrentUser $currentUser, SupportTicket $ticket): bool
    {
        return strcasecmp((string) $ticket->agent, $currentUser->name) === 0
            || strcasecmp((string) $ticket->requester, $currentUser->name) === 0
            || ($currentUser->email !== null && strcasecmp((string) $ticket->requester, $currentUser->email) === 0);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function customerAccessAllowsTicket(array $context, CurrentUser $currentUser, SupportTicket $ticket): bool
    {
        $accessLevels = $this->normalizeStringArray($context['customerAccessLevels'] ?? []);

        if (in_array('Admin', $accessLevels, true)) {
            return true;
        }

        $allowedCustomers = $this->customerNamesFromContext($context);

        if (in_array('CompanyTickets', $accessLevels, true)) {
            return in_array($ticket->customer, $allowedCustomers, true);
        }

        if (in_array('OwnTickets', $accessLevels, true)) {
            return strcasecmp((string) $ticket->requester, $currentUser->name) === 0
                || ($currentUser->email !== null && strcasecmp((string) $ticket->requester, $currentUser->email) === 0);
        }

        return false;
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    private function applyOwnScope(Builder $query, CurrentUser $currentUser): void
    {
        $query->where(function (Builder $builder) use ($currentUser): void {
            $builder->where('agent', $currentUser->name)->orWhere('requester', $currentUser->name);

            if ($currentUser->email !== null) {
                $builder->orWhere('requester', $currentUser->email);
            }
        });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @param  array<string, mixed>  $context
     */
    private function applyCustomerAccessScope(Builder $query, array $context, CurrentUser $currentUser): void
    {
        $accessLevels = $this->normalizeStringArray($context['customerAccessLevels'] ?? []);

        if (in_array('Admin', $accessLevels, true)) {
            return;
        }

        if (in_array('CompanyTickets', $accessLevels, true)) {
            $customers = $this->customerNamesFromContext($context);
            $query->whereIn('customer', $customers ?: ['__no_customer_scope__']);

            return;
        }

        $this->applyOwnScope($query, $currentUser);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultContext(CurrentUser $currentUser): array
    {
        return [
            'userType' => 'Internal',
            'role' => 'Agent',
            'permissions' => self::DEFAULT_TICKET_PERMISSIONS,
            'teamIds' => [],
            'queueIds' => [],
            'customerIds' => [],
            'accessCustomerIds' => [],
            'ticketVisibility' => 'Own',
            'customerAccess' => [],
            'customerAccessLevels' => [],
            'isAdmin' => false,
        ];
    }

    private function accessSchemaReady(): bool
    {
        return Schema::hasTable('support_permission_roles')
            && Schema::hasTable('support_user_scopes')
            && Schema::hasTable('customer_user_accesses')
            && Schema::hasColumns('support_permission_roles', ['user_type', 'role', 'permissions', 'ticket_visibility', 'is_admin'])
            && Schema::hasColumns('support_user_scopes', ['team_ids', 'queue_ids', 'customer_ids'])
            && Schema::hasColumns('customer_user_accesses', ['customer_id', 'access_level']);
    }

    /**
     * @return \Closure(Builder): void
     */
    private function matchCurrentUser(CurrentUser $currentUser): \Closure
    {
        return function (Builder $query) use ($currentUser): void {
            $table = $query->getModel()->getTable();
            $hasUserIdsColumn = Schema::hasColumn($table, 'user_ids');

            $query->where(function (Builder $builder) use ($currentUser): void {
                $hasAnyMatchClause = false;

                if ($currentUser->email !== null && $currentUser->email !== '') {
                    $builder->where('user_email', $currentUser->email);
                    $hasAnyMatchClause = true;
                }

                if (ctype_digit($currentUser->id)) {
                    if ($hasAnyMatchClause) {
                        $builder->orWhere('user_id', (int) $currentUser->id);
                    } else {
                        $builder->where('user_id', (int) $currentUser->id);
                    }

                    $hasAnyMatchClause = true;
                }

                if (! $hasAnyMatchClause) {
                    $builder->whereRaw('1 = 0');
                }
            });

            if ($hasUserIdsColumn && ctype_digit($currentUser->id)) {
                $query->orWhereJsonContains('user_ids', $currentUser->id);
                $query->orWhereJsonContains('user_ids', (int) $currentUser->id);
            }
        };
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int, string>
     */
    private function normalizeStringArray(array $values): array
    {
        return collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function normalizeEnum(?string $candidate, array $allowed, string $fallback): string
    {
        return in_array($candidate, $allowed, true) ? $candidate : $fallback;
    }

    private function normalizePermission(string $permission): string
    {
        $clean = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($permission))) ?? '';

        return match ($clean) {
            'ticketview', 'supportticketview' => 'supportticketview',
            'ticketreply', 'supportticketreply' => 'supportticketreply',
            'ticketinternalnote', 'ticketinternal', 'supportticketinternalnote' => 'supportticketinternalnote',
            'ticketassign', 'supportticketassign' => 'supportticketassign',
            'ticketchangestatus', 'supportticketchangestatus' => 'supportticketchangestatus',
            'ticketchangepriority', 'supportticketchangepriority' => 'supportticketchangepriority',
            'ticketforward', 'supportticketforward' => 'supportticketforward',
            'ticketuploadattachment', 'supportticketuploadattachment' => 'supportticketuploadattachment',
            'ticketbulkupdate', 'supportticketbulkupdate' => 'supportticketbulkupdate',
            default => $clean,
        };
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function toContractPermissions(array $permissions): array
    {
        return collect($permissions)
            ->map(function (string $permission): string {
                return match ($this->normalizePermission($permission)) {
                    'supportticketview' => 'support.ticket.view',
                    'supportticketreply' => 'support.ticket.reply',
                    'supportticketinternalnote' => 'support.ticket.internalNote',
                    'supportticketassign' => 'support.ticket.assign',
                    'supportticketchangestatus' => 'support.ticket.changeStatus',
                    'supportticketchangepriority' => 'support.ticket.changePriority',
                    'supportticketforward' => 'support.ticket.forward',
                    'supportticketuploadattachment' => 'support.ticket.uploadAttachment',
                    'supportticketbulkupdate' => 'support.ticket.bulkUpdate',
                    // Preserve non-ticket permissions (e.g., support.permissions.manage)
                    // so frontend workspace guards can consume them directly.
                    default => trim($permission),
                };
            })
            ->filter(fn (string $permission): bool => $permission !== '')
            ->unique()
            ->values()
            ->all();
    }
}
