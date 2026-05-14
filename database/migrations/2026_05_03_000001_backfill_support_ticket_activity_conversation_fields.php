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
        DB::table('support_ticket_activities')
            ->where('type', 'note')
            ->update([
                'author_name' => DB::raw("coalesce(author_name, 'Amit')"),
                'visibility' => 'internal',
                'is_internal' => true,
            ]);

        DB::table('support_ticket_activities')
            ->where('type', 'reply')
            ->update([
                'author_name' => DB::raw("coalesce(author_name, 'Amit')"),
                'visibility' => 'public',
                'is_internal' => false,
            ]);

        DB::table('support_ticket_activities')
            ->whereNull('author_name')
            ->update(['author_name' => 'System']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('support_ticket_activities')
            ->whereIn('type', ['note', 'reply'])
            ->update([
                'author_name' => null,
                'visibility' => 'public',
                'is_internal' => false,
            ]);
    }
};
