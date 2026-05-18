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
        if (! Schema::hasTable('support_ticket_activity_attachments')) {
            Schema::create('support_ticket_activity_attachments', function (Blueprint $table): void {
                $table->id();
                $table->string('activity_id', 32);
                $table->string('attachment_id', 64);
                $table->timestamps();

                $table->unique(['activity_id', 'attachment_id'], 'sta_activity_attachment_unique');
                $table->index(['attachment_id'], 'sta_attachment_idx');

                $table->foreign('activity_id')
                    ->references('id')
                    ->on('support_ticket_activities')
                    ->cascadeOnDelete();
                $table->foreign('attachment_id')
                    ->references('id')
                    ->on('support_ticket_attachments')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('support_ticket_email_messages')) {
            Schema::create('support_ticket_email_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('support_ticket_id', 32);
                $table->string('activity_id', 32);
                $table->string('provider_message_id', 120)->nullable()->index();
                $table->string('delivery_status', 20)->default('queued')->index();
                $table->json('to_recipients');
                $table->json('cc_recipients')->nullable();
                $table->json('bcc_recipients')->nullable();
                $table->string('subject', 255);
                $table->longText('html_body')->nullable();
                $table->longText('text_body')->nullable();
                $table->dateTime('queued_at')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->text('failed_reason')->nullable();
                $table->timestamps();

                $table->foreign('support_ticket_id')
                    ->references('id')
                    ->on('support_tickets')
                    ->cascadeOnDelete();
                $table->foreign('activity_id')
                    ->references('id')
                    ->on('support_ticket_activities')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('support_ticket_notification_dispatches')) {
            Schema::create('support_ticket_notification_dispatches', function (Blueprint $table): void {
                $table->id();
                $table->string('support_ticket_id', 32);
                $table->string('activity_id', 32)->nullable();
                $table->string('event_type', 40)->index();
                $table->json('channels')->nullable();
                $table->string('job_uuid', 64)->index();
                $table->string('status', 20)->default('queued')->index();
                $table->dateTime('queued_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->text('failed_reason')->nullable();
                $table->timestamps();

                $table->foreign('support_ticket_id')
                    ->references('id')
                    ->on('support_tickets')
                    ->cascadeOnDelete();
                $table->foreign('activity_id')
                    ->references('id')
                    ->on('support_ticket_activities')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('support_attachment_bundle_exports')) {
            Schema::create('support_attachment_bundle_exports', function (Blueprint $table): void {
                $table->string('id', 64)->primary();
                $table->string('support_ticket_id', 32);
                $table->string('disk', 40)->default('local');
                $table->string('path');
                $table->dateTime('expires_at')->nullable()->index();
                $table->string('created_by', 120)->nullable();
                $table->timestamps();

                $table->foreign('support_ticket_id')
                    ->references('id')
                    ->on('support_tickets')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_attachment_bundle_exports');
        Schema::dropIfExists('support_ticket_notification_dispatches');
        Schema::dropIfExists('support_ticket_email_messages');
        Schema::dropIfExists('support_ticket_activity_attachments');
    }
};
