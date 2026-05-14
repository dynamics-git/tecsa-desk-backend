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
        Schema::create('support_teams', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_categories', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_agents', function (Blueprint $table) {
            $table->string('id', 120)->primary();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_agents');
        Schema::dropIfExists('support_categories');
        Schema::dropIfExists('support_teams');
    }
};
