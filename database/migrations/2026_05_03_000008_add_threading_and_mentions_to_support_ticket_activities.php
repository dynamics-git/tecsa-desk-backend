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
            if (! Schema::hasColumn('support_ticket_activities', 'parent_activity_id')) {
                $table->string('parent_activity_id', 32)->nullable()->after('support_ticket_id');
            }

            if (! Schema::hasColumn('support_ticket_activities', 'author_id')) {
                $table->string('author_id', 120)->nullable()->after('author_name');
            }
        });

        if (! Schema::hasTable('support_ticket_activity_mentions')) {
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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_activity_mentions');

        Schema::table('support_ticket_activities', function (Blueprint $table) {
            if (Schema::hasColumn('support_ticket_activities', 'parent_activity_id')) {
                $table->dropColumn('parent_activity_id');
            }

            if (Schema::hasColumn('support_ticket_activities', 'author_id')) {
                $table->dropColumn('author_id');
            }
        });
    }
};
