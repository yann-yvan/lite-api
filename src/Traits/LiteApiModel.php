<?php

namespace Nycorp\LiteApi\Traits;

use Spatie\Translatable\HasTranslations;

/**
 * Trait SearchableTrait
 *
 * @property array $searchable
 * @property array $assets
 * @property array $translatable
 */
trait LiteApiModel
{
    use SearchableTrait;
    //use HasTranslations;

    public function beautifulCounter($number): string
    {
        if ($number < 1000) {
            return $number.'';
        }
        $suffix = ['', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        $power = floor(log($number, 1000));

        return round($number / (1000 ** $power), 1, PHP_ROUND_HALF_EVEN).$suffix[$power];
    }

    public function getAssets(): array
    {
        return $this->assets ?? [];
    }

    public function newInstance($attributes = [], $exists = false)
    {
        $casts = [];
        $assets = [];

        if ($this->hasAssets()) {
            foreach ($this->assets as $item) {
                $assets[$item] = AssetTrait::class;
            }
        }

        if ($this->isTranslatable()) {
            foreach ($this->translatable as $item) {
                $casts[$item] = TranslatableTrait::class;
            }
        }

        $this->mergeCasts($assets);
        $this->mergeCasts($casts);

        return parent::newInstance($attributes, $exists);
    }

    private function isTranslatable(): bool
    {
        return count($this->translatable ?? []) > 0;
    }

    private function hasAssets(): bool
    {
        return count($this->assets ?? []) > 0;
    }
}
