<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('support_ticket_activities')->where('type', 'status')->update(['type' => 'status-change']);
        DB::table('support_ticket_activities')->where('type', 'priority')->update(['type' => 'priority-change']);
        DB::table('support_ticket_activities')->where('type', 'assignment')->update(['type' => 'assignee-change']);
        DB::table('support_ticket_activities')
            ->where('title', 'Linked task created')
            ->update(['type' => 'linked-task-created']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('support_ticket_activities')->where('type', 'status-change')->update(['type' => 'status']);
        DB::table('support_ticket_activities')->where('type', 'priority-change')->update(['type' => 'priority']);
        DB::table('support_ticket_activities')->where('type', 'assignee-change')->update(['type' => 'assignment']);
        DB::table('support_ticket_activities')
            ->where('title', 'Linked task created')
            ->update(['type' => 'forward']);
    }
};
