<?php

namespace Nycorp\LiteApi\Docs;

use Nycorp\LiteApi\Versioning\ApiVersion;
use Nycorp\LiteApi\Versioning\VersionRegistry;

/**
 * One l5-swagger documentation per declared version having a `docs.annotations` block.
 * Each documentation scans its own annotations + the shared ones + its envelope schemas,
 * and excludes the annotation paths of the other versions.
 */
class VersionedDocumentation
{
    public static function register(): void
    {
        $existing = config('l5-swagger.documentations', []);

        // Documentations declared by hand in config/l5-swagger.php win
        config(['l5-swagger.documentations' => $existing + self::documentations(VersionRegistry::fromConfig())]);
    }

    public static function documentations(VersionRegistry $registry): array
    {
        $versions = array_filter($registry->all(), static fn(ApiVersion $version) => !empty($version->docs['annotations']));
        $shared   = config('lite-api.versioning.docs.shared_annotations', []);

        $documentations = [];

        foreach ($versions as $version) {
            $own    = $version->docs['annotations'];
            $others = [];

            foreach ($versions as $other) {
                if ($other->name !== $version->name) {
                    array_push($others, ...$other->docs['annotations']);
                }
            }

            $paths = [
                'docs_json'   => "api-docs-$version->name.json",
                'docs_yaml'   => "api-docs-$version->name.yaml",
                'annotations' => array_values(array_unique(array_filter([...$own, ...$shared, $version->envelope::schemas()]))),
                'excludes'    => array_values(array_diff(array_unique($others), $own)),
            ];

            // l5-swagger adds paths.base as a server of every documentation: point it at the version prefix
            if (isset($version->docs['base'])) {
                $paths['base'] = $version->docs['base'];
            }

            $documentations[$version->name] = [
                'api'    => [
                    'title' => ($version->docs['title'] ?? config('app.name') . ' API') . " $version->name",
                ],
                'routes' => [
                    'api'             => $version->docs['route'] ?? "api/docs/$version->name",
                    // l5-swagger registers these per documentation: shared URIs would override each other
                    'docs'            => config('l5-swagger.defaults.routes.docs', 'docs') . "/$version->name",
                    'oauth2_callback' => config('l5-swagger.defaults.routes.oauth2_callback', 'api/oauth2-callback') . "/$version->name",
                ],
                'paths'  => $paths,
            ];
        }

        return $documentations;
    }
}
