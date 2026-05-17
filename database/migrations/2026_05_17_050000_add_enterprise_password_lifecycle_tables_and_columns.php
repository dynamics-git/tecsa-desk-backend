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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }

            if (! Schema::hasColumn('users', 'password_mode')) {
                $table->string('password_mode', 20)->default('manual')->after('must_change_password');
            }

            if (! Schema::hasColumn('users', 'password_expires_at')) {
                $table->dateTime('password_expires_at')->nullable()->after('password_mode');
            }

            if (! Schema::hasColumn('users', 'password_last_changed_at')) {
                $table->dateTime('password_last_changed_at')->nullable()->after('password_expires_at');
            }

            if (! Schema::hasColumn('users', 'password_never_expires')) {
                $table->boolean('password_never_expires')->default(false)->after('password_last_changed_at');
            }

            if (! Schema::hasColumn('users', 'mfa_required')) {
                $table->boolean('mfa_required')->default(false)->after('password_never_expires');
            }

            if (! Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedInteger('failed_login_attempts')->default(0)->after('mfa_required');
            }

            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->dateTime('locked_until')->nullable()->after('failed_login_attempts');
            }

            if (! Schema::hasColumn('users', 'security_version')) {
                $table->unsignedInteger('security_version')->default(1)->after('locked_until');
            }
        });

        if (! Schema::hasTable('auth_password_policies')) {
            Schema::create('auth_password_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('min_length')->default(12);
                $table->boolean('require_uppercase')->default(true);
                $table->boolean('require_lowercase')->default(true);
                $table->boolean('require_number')->default(true);
                $table->boolean('require_symbol')->default(true);
                $table->boolean('disallow_common_passwords')->default(true);
                $table->unsignedInteger('history_count')->default(5);
                $table->unsignedInteger('max_age_days')->default(90);
                $table->unsignedInteger('lockout_threshold')->default(5);
                $table->unsignedInteger('lockout_duration_minutes')->default(15);
                $table->boolean('allow_password_generate')->default(true);
                $table->boolean('allow_manual_password_set')->default(true);
                $table->boolean('force_change_on_first_login_default')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_password_histories')) {
            Schema::create('user_password_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('password_hash');
                $table->unsignedBigInteger('changed_by')->nullable()->index();
                $table->string('change_source', 30)->default('self');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('security_audit_logs')) {
            Schema::create('security_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->unsignedBigInteger('target_user_id')->nullable()->index();
                $table->string('action', 120)->index();
                $table->string('source_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('user_password_histories');
        Schema::dropIfExists('auth_password_policies');

        Schema::table('users', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'must_change_password',
                'password_mode',
                'password_expires_at',
                'password_last_changed_at',
                'password_never_expires',
                'mfa_required',
                'failed_login_attempts',
                'locked_until',
                'security_version',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
