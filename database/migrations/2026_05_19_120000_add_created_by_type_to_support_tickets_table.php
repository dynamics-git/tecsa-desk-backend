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
        if (! Schema::hasColumn('support_tickets', 'created_by_type')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->string('created_by_type', 20)->default('System')->after('source')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('support_tickets', 'created_by_type')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->dropIndex(['created_by_type']);
                $table->dropColumn('created_by_type');
            });
        }
    }
};
