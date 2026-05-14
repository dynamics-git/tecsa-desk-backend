<?php

namespace App\Support\Repositories;

use App\Models\SupportAgent;
use App\Models\SupportCategory;
use App\Models\SupportCustomer;
use App\Models\SupportMacro;
use App\Models\SupportQueue;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTeam;
use App\Support\Enums\TicketPriority;
use App\Support\Enums\TicketStatus;

final class SupportReferenceDataRepository
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'teams' => $this->teams(),
            'categories' => $this->categories(),
            'agents' => $this->agents(),
            'queues' => $this->queues(),
            'customers' => $this->customers(),
            'slaPolicies' => $this->slaPolicies(),
            'macros' => $this->macros(),
            'priorities' => array_map(fn (TicketPriority $priority): string => $priority->value, TicketPriority::cases()),
            'statuses' => array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases()),
            'sources' => ['Customer Portal', 'Email', 'Phone'],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, isActive: bool}>
     */
    public function teams(): array
    {
        return SupportTeam::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportTeam $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'isActive' => $team->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, isActive: bool}>
     */
    public function categories(): array
    {
        return SupportCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'isActive' => $category->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, email: string|null, isActive: bool}>
     */
    public function agents(): array
    {
        return SupportAgent::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportAgent $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'isActive' => $agent->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, isActive: bool}>
     */
    public function queues(): array
    {
        return SupportQueue::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportQueue $queue): array => [
                'id' => $queue->id,
                'name' => $queue->name,
                'teamId' => $queue->team_id,
                'isActive' => $queue->is_active,
            ])
            ->all();
    }

    public function customers(): array
    {
        return SupportCustomer::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportCustomer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'isActive' => $customer->is_active,
            ])
            ->all();
    }

    public function slaPolicies(): array
    {
        return SupportSlaPolicy::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SupportSlaPolicy $policy): array => [
                'id' => $policy->id,
                'name' => $policy->name,
                'priority' => $policy->priority,
                'firstResponseMinutes' => $policy->first_response_minutes,
                'resolutionMinutes' => $policy->resolution_minutes,
                'isActive' => $policy->is_active,
            ])
            ->all();
    }

    public function macros(): array
    {
        return SupportMacro::query()
            ->orderBy('title')
            ->get()
            ->map(fn (SupportMacro $macro): array => [
                'id' => $macro->id,
                'title' => $macro->title,
                'body' => $macro->body,
                'visibility' => $macro->visibility,
                'isActive' => $macro->is_active,
            ])
            ->all();
    }
}
