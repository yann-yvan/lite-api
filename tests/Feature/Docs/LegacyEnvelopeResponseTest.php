<?php

use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use OpenApi\Generator;
use Psr\Log\NullLogger;

function documented(array $sources): array
{
    $openApi = Generator::scan([...$sources, LegacyEnvelope::schemas()], ['logger' => new NullLogger(), 'version' => '3.1.0']);

    return json_decode($openApi->toJson(), true);
}

it('gives every webhook and hosted session response a typed envelope body', function () {
    $spec = documented([LiteApi::webhookDocs(), LiteApi::hostedSessionDocs()]);

    $untyped = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (!isset($operation['responses']['200']['content']['application/json']['schema'])) {
                $untyped[] = strtoupper($method) . " $path";
            }
        }
    }

    expect($untyped)->toBe([])
        ->and($spec['components']['schemas'])->toHaveKeys(['ApiEnvelope', 'ApiPage']);
});

it('builds the body of a single, listed, paginated or extended resource', function () {
    $spec = documented([dirname(__DIR__, 2) . '/Fixtures/Docs/EnvelopeResponses.php']);
    $body = static fn(string $path) => $spec['paths'][$path]['get']['responses']['200']['content']['application/json']['schema']['allOf'][1]['properties']['body'];

    expect($spec['paths']['/single']['get']['responses']['200']['content']['application/json']['schema']['allOf'][0])->toBe(['$ref' => '#/components/schemas/ApiEnvelope'])
        ->and($body('/single'))->toBe(['$ref' => '#/components/schemas/Widget'])
        ->and($body('/list'))->toBe(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Widget']])
        ->and($body('/page')['allOf'][0])->toBe(['$ref' => '#/components/schemas/ApiPage'])
        ->and($body('/page')['allOf'][1]['properties']['data']['items'])->toBe(['$ref' => '#/components/schemas/Widget'])
        ->and($body('/extended')['allOf'][0])->toBe(['$ref' => '#/components/schemas/Widget'])
        ->and($body('/extended')['allOf'][1]['properties']['secret']['type'])->toBe('string');
});
