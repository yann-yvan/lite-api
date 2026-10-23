<?php

use Nycorp\LiteApi\Docs\VersionedDocumentation;
use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use Nycorp\LiteApi\Response\Envelopes\StandardEnvelope;
use OpenApi\Generator;
use Psr\Log\NullLogger;

it('registers one swagger documentation per documented version', function () {
    config([
        'lite-api.versions'                          => [
            'v1.0' => ['docs' => ['annotations' => ['/app/Docs/V1']]],
            'v2.0' => ['envelope' => StandardEnvelope::class, 'docs' => ['annotations' => ['/app/Docs/V2'], 'route' => 'docs/v2', 'title' => 'Shortext API', 'base' => 'https://api.test/api/v2.0']],
            'v3.0' => [],
        ],
        'lite-api.versioning.docs.shared_annotations' => ['/app/Docs/Shared'],
        'l5-swagger.documentations'                  => ['default' => ['keep' => true], 'v1.0' => ['manual' => true]],
    ]);

    VersionedDocumentation::register();
    $docs = config('l5-swagger.documentations');

    expect(array_keys($docs))->toBe(['default', 'v1.0', 'v2.0'])
        ->and($docs['v1.0'])->toBe(['manual' => true])
        ->and($docs['v2.0']['api']['title'])->toBe('Shortext API v2.0')
        ->and($docs['v2.0']['routes']['api'])->toBe('docs/v2')
        ->and($docs['v2.0']['paths']['docs_json'])->toBe('api-docs-v2.0.json')
        ->and($docs['v2.0']['paths']['annotations'])->toBe(['/app/Docs/V2', '/app/Docs/Shared', StandardEnvelope::schemas()])
        ->and($docs['v2.0']['paths']['excludes'])->toBe(['/app/Docs/V1'])
        ->and($docs['v2.0']['paths']['base'])->toBe('https://api.test/api/v2.0');
});

it('ships envelope schemas serializable in every OpenAPI version', function (string $envelope, string $schema, string $openApiVersion) {
    expect($envelope::schemas())->toBeFile();

    $openApi = Generator::scan([$envelope::schemas()], ['logger' => new NullLogger(), 'version' => $openApiVersion]);
    $json    = json_decode($openApi->toJson(), true);

    expect($json['components']['schemas'])->toHaveKey($schema);
})->with([
    [LegacyEnvelope::class, 'LiteApiLegacyResponse'],
    [StandardEnvelope::class, 'LiteApiError'],
])->with(['3.0.0', '3.1.0']);
