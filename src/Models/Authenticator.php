<?php

namespace Nycorp\LiteApi\Models;

use Illuminate\Database\Eloquent\Model;

class Authenticator extends Model
{
    protected $table = 'authenticators';

    protected $fillable = ['email', 'token', 'code', 'model'];
}
