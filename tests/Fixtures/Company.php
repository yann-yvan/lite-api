<?php

namespace Nycorp\LiteApi\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Nycorp\LiteApi\Webhooks\Concerns\HasWebhookEndpoints;

class Company extends User
{
    use HasWebhookEndpoints;

    protected $table   = 'companies';
    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
