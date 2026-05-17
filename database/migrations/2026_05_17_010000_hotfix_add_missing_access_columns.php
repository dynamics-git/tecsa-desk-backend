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
        Schema::table('support_permission_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('support_permission_roles', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('support_permission_roles', 'user_email')) {
                $table->string('user_email')->nullable()->index()->after('user_id');
            }

            if (! Schema::hasColumn('support_permission_roles', 'user_type')) {
                $table->string('user_type', 20)->default('Internal')->after('user_email');
            }

            if (! Schema::hasColumn('support_permission_roles', 'role')) {
                $table->string('role', 40)->default('Agent')->after('user_type');
            }

            if (! Schema::hasColumn('support_permission_roles', 'permissions')) {
                $table->json('permissions')->nullable()->after('role');
            }

            if (! Schema::hasColumn('support_permission_roles', 'ticket_visibility')) {
                $table->string('ticket_visibility', 30)->default('Own')->after('permissions');
            }

            if (! Schema::hasColumn('support_permission_roles', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('ticket_visibility');
            }
        });

        Schema::table('support_user_scopes', function (Blueprint $table) {
            if (! Schema::hasColumn('support_user_scopes', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('support_user_scopes', 'user_email')) {
                $table->string('user_email')->nullable()->index()->after('user_id');
            }

            if (! Schema::hasColumn('support_user_scopes', 'team_ids')) {
                $table->json('team_ids')->nullable()->after('user_email');
            }

            if (! Schema::hasColumn('support_user_scopes', 'queue_ids')) {
                $table->json('queue_ids')->nullable()->after('team_ids');
            }

            if (! Schema::hasColumn('support_user_scopes', 'customer_ids')) {
                $table->json('customer_ids')->nullable()->after('queue_ids');
            }
        });

        Schema::table('customer_user_accesses', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_user_accesses', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            }

            if (! Schema::hasColumn('customer_user_accesses', 'user_email')) {
                $table->string('user_email')->nullable()->index()->after('user_id');
            }

            if (! Schema::hasColumn('customer_user_accesses', 'customer_id')) {
                $table->string('customer_id', 120)->nullable()->index()->after('user_email');
            }

            if (! Schema::hasColumn('customer_user_accesses', 'access_level')) {
                $table->string('access_level', 30)->default('OwnTickets')->after('customer_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op for hotfix safety.
    }
};
