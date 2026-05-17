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
        $tableName = Schema::hasTable('support_user_scope')
            ? 'support_user_scope'
            : (Schema::hasTable('support_user_scopes') ? 'support_user_scopes' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn($tableName, 'visibility_mode')) {
                $table->string('visibility_mode', 30)->default('Own')->after('user_email');
            }

            if (! Schema::hasColumn($tableName, 'team_ids')) {
                $table->json('team_ids')->nullable()->after('visibility_mode');
            }

            if (! Schema::hasColumn($tableName, 'queue_ids')) {
                $table->json('queue_ids')->nullable()->after('team_ids');
            }

            if (! Schema::hasColumn($tableName, 'customer_ids')) {
                $table->json('customer_ids')->nullable()->after('queue_ids');
            }

            if (! Schema::hasColumn($tableName, 'is_active')) {
                $table->boolean('is_active')->default(true)->after('customer_ids');
            }

            try {
                $table->index('user_id', 'idx_support_user_scope_user');
            } catch (\Throwable) {
                // Index may already exist.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('support_user_scope')
            ? 'support_user_scope'
            : (Schema::hasTable('support_user_scopes') ? 'support_user_scopes' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            try {
                $table->dropIndex('idx_support_user_scope_user');
            } catch (\Throwable) {
                // Ignore if index is missing.
            }

            $dropColumns = [];

            foreach (['user_name', 'visibility_mode', 'team_ids', 'queue_ids', 'customer_ids', 'is_active'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
