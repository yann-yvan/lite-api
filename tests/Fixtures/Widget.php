<?php

namespace Nycorp\LiteApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Widget extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'company_id'];

    public static function createTable(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /** SearchableTrait builds MySQL relevance queries: out of scope here */
    public function scopeSearch(Builder $query, ...$arguments): Builder
    {
        return $query;
    }

    public function getAssets(): array
    {
        return [];
    }
}
