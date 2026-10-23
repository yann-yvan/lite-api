<?php

use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use Nycorp\LiteApi\Response\Envelopes\StandardEnvelope;

return [

    'versioning' => [
        /*
         * Response header carrying the version that served the request
         */
        'header' => 'X-Api-Version',

        /*
         * Envelope used outside any versioned route (unversioned routes, queued jobs, console)
         */
        'default_envelope' => LegacyEnvelope::class,

        /*
         * Annotation paths added to every versioned documentation (shared schemas...)
         */
        'docs' => [
            'shared_annotations' => [],
        ],
    ],

    /*
     * Declared API versions, in release order. A version is a frozen contract:
     * only additive changes are allowed once released, anything else needs a new version.
     *
     * Status is computed from the dates: active, deprecated once deprecated_at is reached,
     * sunset (HTTP 410) once sunset_at is reached.
     *
     * 'v2.0' => [
     *     'envelope'      => StandardEnvelope::class,
     *     'released_at'   => '2026-11-01',
     *     'deprecated_at' => null,
     *     'sunset_at'     => null,
     *     'changelog'     => 'https://example.com/docs/changelog#v2.0',
     *     'docs'          => [
     *         'annotations' => [app_path('Http/Docs/V2')],
     *         'route'       => 'api/docs/v2.0',
     *         'title'       => 'My API',
     *     ],
     * ],
     */
    'versions' => [],

    /*
     * Response code => HTTP status overrides for envelopes using real HTTP statuses
     */
    'http_status' => [],
];
