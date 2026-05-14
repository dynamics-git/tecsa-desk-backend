<?php

namespace Database\Seeders;

use App\Models\SupportAgent;
use App\Models\SupportCategory;
use App\Models\SupportCustomer;
use App\Models\SupportMacro;
use App\Models\SupportQueue;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTeam;
use Illuminate\Database\Seeder;

class SupportReferenceDataSeeder extends Seeder
{
    /**
     * Seed support operational master data used by dropdowns and filters.
     */
    public function run(): void
    {
        foreach ($this->teams() as $team) {
            SupportTeam::query()->updateOrCreate(['id' => $team['id']], $team);
        }

        foreach ($this->categories() as $category) {
            SupportCategory::query()->updateOrCreate(['id' => $category['id']], $category);
        }

        foreach ($this->agents() as $agent) {
            SupportAgent::query()->updateOrCreate(['id' => $agent['id']], $agent);
        }

        foreach ($this->customers() as $customer) {
            SupportCustomer::query()->updateOrCreate(['id' => $customer['id']], $customer);
        }

        foreach ($this->queues() as $queue) {
            SupportQueue::query()->updateOrCreate(['id' => $queue['id']], $queue);
        }

        foreach ($this->slaPolicies() as $policy) {
            SupportSlaPolicy::query()->updateOrCreate(['id' => $policy['id']], $policy);
        }

        foreach ($this->macros() as $macro) {
            SupportMacro::query()->updateOrCreate(['id' => $macro['id']], $macro);
        }
    }

    /**
     * @return array<int, array{id: string, name: string, is_active: bool}>
     */
    private function teams(): array
    {
        return [
            ['id' => 'portal-support', 'name' => 'Portal Support', 'is_active' => true],
            ['id' => 'billing-support', 'name' => 'Billing Support', 'is_active' => true],
            ['id' => 'integration-support', 'name' => 'Integration Support', 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, is_active: bool}>
     */
    private function categories(): array
    {
        return [
            ['id' => 'claims-attachments', 'name' => 'Claims / Attachments', 'is_active' => true],
            ['id' => 'access-password-reset', 'name' => 'Access / Password Reset', 'is_active' => true],
            ['id' => 'billing-documents', 'name' => 'Billing / Documents', 'is_active' => true],
            ['id' => 'api-callbacks', 'name' => 'API / Callbacks', 'is_active' => true],
            ['id' => 'fc', 'name' => 'FC', 'is_active' => true],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, email: string, is_active: bool}>
     */
    private function agents(): array
    {
        return [
            ['id' => 'amit', 'name' => 'Amit', 'email' => 'amit@example.com', 'is_active' => true],
            ['id' => 'priya', 'name' => 'Priya', 'email' => 'priya@example.com', 'is_active' => true],
            ['id' => 'mei-lin', 'name' => 'Mei Lin', 'email' => 'mei.lin@example.com', 'is_active' => true],
            ['id' => 'sarah', 'name' => 'Sarah', 'email' => 'sarah@example.com', 'is_active' => true],
        ];
    }

    private function customers(): array
    {
        return [
            ['id' => 'hlib', 'name' => 'HLIB', 'email' => 'support@hlib.example.com', 'phone' => null, 'is_active' => true],
            ['id' => 'acme-health', 'name' => 'Acme Health', 'email' => 'help@acme-health.example.com', 'phone' => null, 'is_active' => true],
            ['id' => 'globex', 'name' => 'Globex', 'email' => 'support@globex.example.com', 'phone' => null, 'is_active' => true],
            ['id' => 'northwind', 'name' => 'Northwind', 'email' => 'support@northwind.example.com', 'phone' => null, 'is_active' => true],
        ];
    }

    private function queues(): array
    {
        return [
            ['id' => 'all-open', 'name' => 'All open', 'team_id' => null, 'is_active' => true],
            ['id' => 'assigned-to-me', 'name' => 'Assigned to me', 'team_id' => null, 'is_active' => true],
            ['id' => 'waiting-on-customer', 'name' => 'Waiting on customer', 'team_id' => null, 'is_active' => true],
            ['id' => 'forwarded', 'name' => 'Forwarded', 'team_id' => null, 'is_active' => true],
            ['id' => 'portal-support', 'name' => 'Portal Support', 'team_id' => 'portal-support', 'is_active' => true],
        ];
    }

    private function slaPolicies(): array
    {
        return [
            ['id' => 'high-priority', 'name' => 'High priority', 'priority' => 'High', 'first_response_minutes' => 60, 'resolution_minutes' => 480, 'is_active' => true],
            ['id' => 'urgent-priority', 'name' => 'Urgent priority', 'priority' => 'Urgent', 'first_response_minutes' => 15, 'resolution_minutes' => 180, 'is_active' => true],
        ];
    }

    private function macros(): array
    {
        return [
            ['id' => 'request-more-info', 'title' => 'Request more information', 'body' => 'Could you please share more details so we can investigate?', 'visibility' => 'public', 'is_active' => true],
            ['id' => 'internal-escalation-note', 'title' => 'Internal escalation note', 'body' => 'Escalating internally for specialist review.', 'visibility' => 'internal', 'is_active' => true],
        ];
    }
}
