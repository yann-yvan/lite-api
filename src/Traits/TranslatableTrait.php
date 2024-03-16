<?php


namespace Nycorp\LiteApi\Traits;


use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class TranslatableTrait implements Castable
{
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes {

            public function get($model, string $key, $value, array $attributes)
            {
                try {
                    //Get preferred language
                    return json_decode($attributes[$key], true)[app()->getLocale()];
                } catch (\Exception | \Throwable $exception) {
                    try {
                        //Try fallback language
                        return json_decode($attributes[$key], true)[config('app.fallback_locale')];
                    } catch (\Exception | \Throwable $exception) {
                        return $value;
                    }
                }
            }

            public function set($model, string $key, $value, array $attributes)
            {
                try {
                    //Get preferred language
                    $data= json_decode($attributes[$key], true);
                    $data[app()->getLocale()] = $value;
                    return json_encode($data);
                } catch (\Exception | \Throwable $exception) {
                    $val =[];
                    $val[app()->getLocale()] = $value;
                    return json_encode($val);
                }
            }
        };
    }
}
