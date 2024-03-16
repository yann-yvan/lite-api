<?php


namespace Nycorp\LiteApi\Models;


class ResetPassword extends LiteApiModel
{
    protected $table = "verifications";

    protected $fillable = ["email", "token", "code", "model"];
}
