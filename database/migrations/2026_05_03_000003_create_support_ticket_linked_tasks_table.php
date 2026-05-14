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
        Schema::create('support_ticket_linked_tasks', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('support_ticket_id', 32);
            $table->string('title');
            $table->string('assignee', 120)->nullable();
            $table->string('status', 40)->default('Open');
            $table->text('comment')->nullable();
            $table->json('attachment_ids')->nullable();
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
        Schema::dropIfExists('support_ticket_linked_tasks');
    }
};
