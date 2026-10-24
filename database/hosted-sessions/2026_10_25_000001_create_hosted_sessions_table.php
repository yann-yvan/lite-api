<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hosted sessions: a signed, expiring link to a page hosted by the application (connect a channel,
 * build a template...) that an API client hands to its own user.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hosted_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('owner');
            $table->nullableMorphs('actor');
            $table->string('purpose', 64)->index();
            // sha256 of the token: the token itself is only in the URL given at creation
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->string('error', 1000)->nullable();
            $table->string('return_url', 2048)->nullable();
            $table->string('locale', 10)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosted_sessions');
    }
};
