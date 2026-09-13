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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('Primary key of the user account.');
            $table->string('name')->comment('Display name of the user.');
            $table->string('email')->unique()->comment('Unique email address used for authentication.');
            $table->timestamp('email_verified_at')->nullable()->comment('Timestamp when the email address was verified.');
            $table->string('password')->comment('Hashed password used for authentication.');
            $table->rememberToken()->comment('Token used for persistent login sessions.');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Email address requesting a password reset.');
            $table->string('token')->comment('Hashed password reset token.');
            $table->timestamp('created_at')->nullable()->comment('Timestamp when the reset token was created.');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Primary identifier of the application session.');
            $table->foreignId('user_id')->nullable()->index()->comment('User associated with the session, when authenticated.');
            $table->string('ip_address', 45)->nullable()->comment('Client IP address associated with the session.');
            $table->text('user_agent')->nullable()->comment('Client user agent associated with the session.');
            $table->longText('payload')->comment('Serialized session data.');
            $table->integer('last_activity')->index()->comment('Unix timestamp of the most recent session activity.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
