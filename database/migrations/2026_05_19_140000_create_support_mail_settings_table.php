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
        if (Schema::hasTable('support_mail_settings')) {
            return;
        }

        Schema::create('support_mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mailer', 32)->default('smtp');
            $table->string('host', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption', 16)->nullable();
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('reply_to_address', 255)->nullable();
            $table->unsignedInteger('timeout')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_mail_settings');
    }
};
