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
        if (Schema::hasColumn('support_ticket_activities', 'author_name')) {
            return;
        }

        Schema::table('support_ticket_activities', function (Blueprint $table) {
            $table->string('author_name', 120)->nullable()->after('message');
            $table->string('visibility', 20)->default('public')->after('author_name');
            $table->boolean('is_internal')->default(false)->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('support_ticket_activities', 'author_name')) {
            return;
        }

        Schema::table('support_ticket_activities', function (Blueprint $table) {
            $table->dropColumn(['author_name', 'visibility', 'is_internal']);
        });
    }
};
