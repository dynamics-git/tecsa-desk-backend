<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('support_ticket_activities')) {
            return;
        }

        Schema::table('support_ticket_activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_ticket_activities', 'html_body')) {
                $table->longText('html_body')->nullable()->after('message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('support_ticket_activities')) {
            return;
        }

        Schema::table('support_ticket_activities', function (Blueprint $table): void {
            if (Schema::hasColumn('support_ticket_activities', 'html_body')) {
                $table->dropColumn('html_body');
            }
        });
    }
};
