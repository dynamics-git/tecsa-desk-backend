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
        if (Schema::hasTable('support_activity_reads')) {
            return;
        }

        Schema::create('support_activity_reads', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_id', 32);
            $table->string('user_id', 120);
            $table->dateTime('read_at');
            $table->timestamps();

            $table->unique(['activity_id', 'user_id'], 'support_activity_reads_activity_user_unique');
            $table->index(['user_id'], 'support_activity_reads_user_idx');

            $table->foreign('activity_id')
                ->references('id')
                ->on('support_ticket_activities')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_activity_reads');
    }
};
