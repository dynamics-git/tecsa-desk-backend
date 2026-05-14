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
        Schema::table('support_ticket_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('support_ticket_activities', 'related_entity_id')) {
                $table->string('related_entity_id', 64)->nullable()->after('is_internal');
            }
        });

        Schema::table('support_ticket_linked_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('support_ticket_linked_tasks', 'status')) {
                $table->string('status', 40)->default('Open')->after('assignee');
            }
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('support_ticket_id', 32)->nullable();
            $table->string('file_name');
            $table->string('disk', 40)->default('local');
            $table->string('path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('uploaded_by', 120);
            $table->string('customer', 120)->nullable();
            $table->string('requester', 120)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table
                ->foreign('support_ticket_id')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');

        Schema::table('support_ticket_linked_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('support_ticket_linked_tasks', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('support_ticket_activities', function (Blueprint $table) {
            if (Schema::hasColumn('support_ticket_activities', 'related_entity_id')) {
                $table->dropColumn('related_entity_id');
            }
        });
    }
};
