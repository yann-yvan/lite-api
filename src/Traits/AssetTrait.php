<?php


namespace Nycorp\LiteApi\Traits;


use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class AssetTrait implements Castable
{
    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes {

            public function get($model, string $key, $value, array $attributes)
            {
                if (empty($attributes[$key])) {
                    return $attributes[$key];
                }else {
                    try{
                        $paths = [];
                        foreach (json_decode($attributes[$key],true) as $path)
                            $paths[] = asset($path);
                        return $paths;
                    }catch (\Exception | \Throwable $exception){
                        return   asset($attributes[$key]);
                    }
                }
            }

            public function set($model, string $key, $value, array $attributes)
            {
                return $value;
            }
        };
    }
}
