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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('subject');
            $table->string('submeta');
            $table->string('customer', 120);
            $table->string('priority', 20)->index();
            $table->string('status', 40)->index();
            $table->string('agent', 80)->nullable()->index();
            $table->string('requester', 120);
            $table->string('team', 120)->index();
            $table->string('source', 120);
            $table->string('category', 120)->index();
            $table->boolean('is_assigned_to_me')->default(false);
            $table->boolean('is_waiting_on_customer')->default(false);
            $table->timestamp('sla_first_response_at')->nullable();
            $table->timestamp('sla_resolution_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_activities', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('support_ticket_id', 32);
            $table->string('parent_activity_id', 32)->nullable();
            $table->string('title');
            $table->string('type', 40)->index();
            $table->text('message')->nullable();
            $table->string('author_name', 120)->nullable();
            $table->string('author_id', 120)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->boolean('is_internal')->default(false);
            $table->string('related_entity_id', 64)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table
                ->foreign('support_ticket_id')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnDelete();
        });

        Schema::create('support_ticket_activity_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('activity_id', 32);
            $table->string('mentioned_user_id', 120)->nullable();
            $table->string('mentioned_team_id', 120)->nullable();
            $table->string('display_name', 120);
            $table->string('kind', 20);
            $table->timestamps();

            $table
                ->foreign('activity_id')
                ->references('id')
                ->on('support_ticket_activities')
                ->cascadeOnDelete();
        });

        Schema::create('support_ticket_related_items', function (Blueprint $table) {
            $table->id();
            $table->string('support_ticket_id', 32);
            $table->string('related_id', 32);
            $table->string('title');
            $table->string('meta');
            $table->timestamps();

            $table
                ->foreign('support_ticket_id')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnDelete();

            $table->unique(['support_ticket_id', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_related_items');
        Schema::dropIfExists('support_ticket_activity_mentions');
        Schema::dropIfExists('support_ticket_activities');
        Schema::dropIfExists('support_tickets');
    }
};
