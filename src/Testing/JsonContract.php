<?php

namespace Nycorp\LiteApi\Testing;

/**
 * Shape of a JSON document (types and keys, never values) and the breaking-change check
 * between a recorded shape and a new one. Additions are allowed, removals and type changes are not.
 *
 * Shape: ['types' => [...], 'properties' => [key => shape]?, 'items' => shape|null?]
 */
class JsonContract
{
    public static function describe(mixed $value): array
    {
        if (is_array($value)) {
            if ($value === [] || array_is_list($value)) {
                $items = null;
                foreach ($value as $item) {
                    $items = $items === null ? self::describe($item) : self::merge($items, self::describe($item));
                }

                return ['types' => ['list'], 'items' => $items];
            }

            return ['types' => ['object'], 'properties' => array_map(self::describe(...), $value)];
        }

        return ['types' => [match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            default => 'string',
        }]];
    }

    public static function merge(array $a, array $b): array
    {
        $merged = ['types' => array_values(array_unique([...$a['types'], ...$b['types']]))];
        sort($merged['types']);

        if (isset($a['properties']) || isset($b['properties'])) {
            $properties = $a['properties'] ?? [];
            foreach ($b['properties'] ?? [] as $key => $shape) {
                $properties[$key] = isset($properties[$key]) ? self::merge($properties[$key], $shape) : $shape;
            }
            $merged['properties'] = $properties;
        }

        if (array_key_exists('items', $a) || array_key_exists('items', $b)) {
            $items           = array_values(array_filter([$a['items'] ?? null, $b['items'] ?? null]));
            $merged['items'] = count($items) === 2 ? self::merge($items[0], $items[1]) : ($items[0] ?? null);
        }

        return $merged;
    }

    /**
     * @return string[] breaking changes, empty when $actual honours $expected
     */
    public static function breakingChanges(array $expected, array $actual, string $path = '$'): array
    {
        $allowed = $expected['types'];
        $types   = $actual['types'];

        if ($types === ['null']) {
            return in_array('null', $allowed, true) ? [] : ["$path became null"];
        }

        // PHP serializes an empty object as []
        if ($types === ['list'] && ($actual['items'] ?? null) === null && in_array('object', $allowed, true)) {
            return [];
        }

        $unexpected = array_diff(array_diff($types, ['null']), $allowed);
        if ($unexpected !== []) {
            return ["$path changed type from " . implode('|', $allowed) . ' to ' . implode('|', $types)];
        }

        $changes = [];

        foreach ($expected['properties'] ?? [] as $key => $shape) {
            if (!array_key_exists($key, $actual['properties'] ?? [])) {
                $changes[] = "$path.$key was removed";
                continue;
            }
            array_push($changes, ...self::breakingChanges($shape, $actual['properties'][$key], "$path.$key"));
        }

        if (isset($expected['items'], $actual['items'])) {
            array_push($changes, ...self::breakingChanges($expected['items'], $actual['items'], "{$path}[]"));
        }

        return $changes;
    }
}
