<?php


namespace Nycorp\LiteApi\Traits;


use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Str;

class OriginTrait implements Castable
{
    const SUFFIX = "_origin";

    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes {

            public function get($model, string $key, $value, array $attributes)
            {
                return $attributes[Str::replace(OriginTrait::SUFFIX,"",$key)];
            }

            public function set($model, string $key, $value, array $attributes)
            {
                return $value;
            }
        };
    }
}
