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
        $tableName = Schema::hasTable('customer_user_access')
            ? 'customer_user_access'
            : (Schema::hasTable('customer_user_accesses') ? 'customer_user_accesses' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn($tableName, 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_id');
            }

            if (! Schema::hasColumn($tableName, 'can_create_ticket')) {
                $table->boolean('can_create_ticket')->default(false)->after('access_level');
            }

            if (! Schema::hasColumn($tableName, 'can_view_attachments')) {
                $table->boolean('can_view_attachments')->default(false)->after('can_create_ticket');
            }

            if (! Schema::hasColumn($tableName, 'can_reply')) {
                $table->boolean('can_reply')->default(false)->after('can_view_attachments');
            }

            if (! Schema::hasColumn($tableName, 'is_active')) {
                $table->boolean('is_active')->default(true)->after('can_reply');
            }

            try {
                $table->index('user_id', 'idx_customer_user_access_user');
            } catch (\Throwable) {
                // Index may already exist.
            }

            try {
                $table->index('customer_id', 'idx_customer_user_access_customer');
            } catch (\Throwable) {
                // Index may already exist.
            }

            try {
                $table->unique(['user_id', 'customer_id'], 'customer_user_unique');
            } catch (\Throwable) {
                // Unique may already exist.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('customer_user_access')
            ? 'customer_user_access'
            : (Schema::hasTable('customer_user_accesses') ? 'customer_user_accesses' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            try {
                $table->dropUnique('customer_user_unique');
            } catch (\Throwable) {
                // Ignore if unique is missing.
            }

            try {
                $table->dropIndex('idx_customer_user_access_user');
            } catch (\Throwable) {
                // Ignore if index is missing.
            }

            try {
                $table->dropIndex('idx_customer_user_access_customer');
            } catch (\Throwable) {
                // Ignore if index is missing.
            }

            $dropColumns = [];

            foreach (['user_name', 'customer_name', 'can_create_ticket', 'can_view_attachments', 'can_reply', 'is_active'] as $column) {
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
