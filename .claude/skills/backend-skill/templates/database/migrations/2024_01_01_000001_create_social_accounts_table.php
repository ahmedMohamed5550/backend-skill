<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Social accounts table (optional Social Login feature)
|--------------------------------------------------------------------------
|
| Copy this migration into database/migrations only when the project
| requirements explicitly request Social Login (references/authentication.md,
| Social Login (Socialite)), renaming it with the current timestamp so it
| runs after the users table migration.
|
| The unique (provider, provider_user_id) pair is the identity lookup key;
| email alone never resolves an identity. Add optional encrypted provider
| token columns only when the business requirements require provider API
| access (config/social.php, store_provider_tokens).
|
*/

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->unique(['provider', 'provider_user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
