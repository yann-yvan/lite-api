<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('owner');
            $table->string('url', 2048);
            $table->text('secret')->comment('encrypted');
            $table->json('events');
            $table->json('filters')->nullable()->comment('event context attributes the endpoint is restricted to');
            $table->string('api_version', 20)->nullable()->comment('payload version, null = default');
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('failing_since')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
