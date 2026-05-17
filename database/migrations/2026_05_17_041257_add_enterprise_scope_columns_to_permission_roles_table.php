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
        $tableName = Schema::hasTable('permission_roles')
            ? 'permission_roles'
            : (Schema::hasTable('support_permission_roles') ? 'support_permission_roles' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'user_ids')) {
                $table->json('user_ids')->nullable()->after('permissions');
            }

            if (! Schema::hasColumn($tableName, 'team_ids')) {
                $table->json('team_ids')->nullable()->after('user_ids');
            }

            if (! Schema::hasColumn($tableName, 'customer_ids')) {
                $table->json('customer_ids')->nullable()->after('team_ids');
            }

            if (! Schema::hasColumn($tableName, 'is_active')) {
                $table->boolean('is_active')->default(true)->after('customer_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('permission_roles')
            ? 'permission_roles'
            : (Schema::hasTable('support_permission_roles') ? 'support_permission_roles' : null);

        if ($tableName === null) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $dropColumns = [];

            foreach (['user_ids', 'team_ids', 'customer_ids', 'is_active'] as $column) {
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
