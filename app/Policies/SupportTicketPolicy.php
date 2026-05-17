<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\SupportAccessResolver;

class SupportTicketPolicy
{
    public function __construct(
        private readonly SupportAccessResolver $supportAccessResolver,
    ) {}

    public function viewAny(User $user): bool
    {
        $currentUser = $this->toCurrentUser($user);

        return $this->supportAccessResolver->hasPermission($currentUser, 'support.ticket.view');
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.view');
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.reply');
    }

    public function internalNote(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.internalNote');
    }

    public function assign(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.assign');
    }

    public function changeStatus(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.changeStatus');
    }

    public function changePriority(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.changePriority');
    }

    public function forward(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.forward');
    }

    public function uploadAttachment(User $user, SupportTicket $supportTicket): bool
    {
        return $this->canAct($user, $supportTicket, 'support.ticket.uploadAttachment');
    }

    public function bulkUpdate(User $user): bool
    {
        return $this->supportAccessResolver->hasPermission($this->toCurrentUser($user), 'support.ticket.bulkUpdate');
    }

    private function canAct(User $user, SupportTicket $supportTicket, string $permission): bool
    {
        $currentUser = $this->toCurrentUser($user);

        return $this->supportAccessResolver->canViewTicket($currentUser, $supportTicket)
            && $this->supportAccessResolver->hasPermission($currentUser, $permission);
    }

    private function toCurrentUser(User $user): CurrentUser
    {
        return new CurrentUser(
            id: (string) ($user->getKey() ?? ''),
            name: (string) ($user->name ?? ''),
            email: $user->email,
        );
    }
}
