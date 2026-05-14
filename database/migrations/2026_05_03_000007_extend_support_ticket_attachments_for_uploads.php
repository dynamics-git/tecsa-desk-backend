<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_ticket_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('support_ticket_attachments', 'disk')) {
                $table->string('disk', 40)->default('local')->after('file_name');
            }

            if (! Schema::hasColumn('support_ticket_attachments', 'path')) {
                $table->string('path')->nullable()->after('disk');
            }

            if (! Schema::hasColumn('support_ticket_attachments', 'mime_type')) {
                $table->string('mime_type', 120)->nullable()->after('path');
            }

            if (! Schema::hasColumn('support_ticket_attachments', 'customer')) {
                $table->string('customer', 120)->nullable()->after('uploaded_by');
            }

            if (! Schema::hasColumn('support_ticket_attachments', 'requester')) {
                $table->string('requester', 120)->nullable()->after('customer');
            }
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table support_ticket_attachments modify support_ticket_id varchar(32) null');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_ticket_attachments', function (Blueprint $table) {
            $table->dropColumn(['disk', 'path', 'mime_type', 'customer', 'requester']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('alter table support_ticket_attachments modify support_ticket_id varchar(32) not null');
        }
    }
};
