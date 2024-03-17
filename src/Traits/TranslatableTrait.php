<?php

namespace Nycorp\LiteApi\Traits;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class TranslatableTrait implements Castable
{
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes)
            {
                try {
                    // Get preferred language
                    return json_decode($attributes[$key], true)[app()->getLocale()];
                } catch (\Exception|\Throwable) {
                    try {

                        $value = json_decode($attributes[$key], true);

                        // Try fallback language
                        if (array_key_exists(app()->getFallbackLocale(), $value)) {
                            return $value[app()->getFallbackLocale()];
                        }

                        // Try first language
                        return $value[array_key_first($value)];
                    } catch (\Exception|\Throwable) {
                        return $value;
                    }
                }
            }

            public function set($model, string $key, $value, array $attributes)
            {
                try {
                    // Get preferred language
                    $data = json_decode($attributes[$key], true);
                    $data[app()->getLocale()] = $value;

                    return json_encode($data);
                } catch (\Exception|\Throwable) {
                    $val = [];
                    $val[app()->getLocale()] = $value;

                    return json_encode($val);
                }
            }
        };
    }
}
