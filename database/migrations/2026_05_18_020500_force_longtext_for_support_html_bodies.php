<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('support_ticket_activities') && Schema::hasColumn('support_ticket_activities', 'html_body')) {
            DB::statement('ALTER TABLE support_ticket_activities MODIFY html_body LONGTEXT NULL');
        }

        if (Schema::hasTable('support_ticket_email_messages') && Schema::hasColumn('support_ticket_email_messages', 'html_body')) {
            DB::statement('ALTER TABLE support_ticket_email_messages MODIFY html_body LONGTEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('support_ticket_activities') && Schema::hasColumn('support_ticket_activities', 'html_body')) {
            DB::statement('ALTER TABLE support_ticket_activities MODIFY html_body TEXT NULL');
        }

        if (Schema::hasTable('support_ticket_email_messages') && Schema::hasColumn('support_ticket_email_messages', 'html_body')) {
            DB::statement('ALTER TABLE support_ticket_email_messages MODIFY html_body TEXT NULL');
        }
    }
};
