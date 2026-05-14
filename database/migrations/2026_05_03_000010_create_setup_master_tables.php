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
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('email');
            }
        });

        Schema::create('support_customers', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_queues', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->string('team_id', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_sla_policies', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->string('priority', 20)->nullable();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_macros', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('title', 160);
            $table->text('body');
            $table->string('visibility', 20)->default('internal');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_macros');
        Schema::dropIfExists('support_sla_policies');
        Schema::dropIfExists('support_queues');
        Schema::dropIfExists('support_customers');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
