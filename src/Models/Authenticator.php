<?php

namespace Nycorp\LiteApi\Models;

use Illuminate\Database\Eloquent\Model;

class Authenticator extends Model
{
    const USERNAME = 'username';
    const MODEL = 'model';
    const TOKEN = 'token';
    const CODE = 'code';
    protected $table = 'authenticators';
    protected $fillable = ['username', 'token', 'code', 'model'];
}
