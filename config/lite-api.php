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
     *         'base'        => env('APP_URL') . '/api/v2.0',   // server added by l5-swagger
     *     ],
     * ],
     */
    'versions' => [],

    /*
     * Response code => HTTP status overrides for envelopes using real HTTP statuses
     */
    'http_status' => [],

    /*
     * Outbound webhooks (opt-in: php artisan vendor:publish --tag=lite-api-webhooks && php artisan migrate)
     */
    'webhooks' => [
        'queue'               => env('LITE_API_WEBHOOK_QUEUE', 'webhooks'),
        'connection'          => env('LITE_API_WEBHOOK_CONNECTION'),

        // Seconds to get a 2xx; the receiver must answer fast and process asynchronously
        'timeout'             => 10,

        // Delay before each retry, in seconds: 10s, 1min, 5min, 30min, 2h, 6h, 12h (~21h in total)
        'backoff'             => [10, 60, 300, 1800, 7200, 21600, 43200],

        // An endpoint failing without interruption for this long is disabled
        'disable_after_hours' => 72,

        // Deliveries older than this are pruned (schedule model:prune)
        'retention_days'      => 30,

        // Headers: {prefix}-Event, -Delivery, -Timestamp, -Signature, -Api-Version
        'header_prefix'       => 'X-Webhook',

        // SSRF protection: refuse URLs resolving to private/reserved addresses (allow only in local dev)
        'allow_private_urls'  => env('LITE_API_WEBHOOK_ALLOW_PRIVATE_URLS', false),
        'require_https'       => env('LITE_API_WEBHOOK_REQUIRE_HTTPS', true),

        /*
         * Catalog of the events an endpoint can subscribe to: type => description.
         * webhook.test is always available.
         *
         * 'message.received' => 'A contact sent a message',
         */
        'events'              => [],
    ],

    /*
     * Hosted sessions (opt-in: php artisan vendor:publish --tag=lite-api-hosted-sessions && php artisan migrate):
     * a signed, expiring link to a page of the application that an API client gives to its user.
     */
    'hosted_sessions' => [
        // Named route of the page, with a {token} parameter and the lite-api.hosted-session middleware
        'route'                    => 'lite-api.hosted-session',

        'ttl_minutes'              => 60,
        'max_ttl_minutes'          => 1440,

        // The return URL of the client must be https (allow http only in local development)
        'require_https_return_url' => env('LITE_API_HOSTED_SESSION_REQUIRE_HTTPS', true),

        /*
         * What a session can be for: purpose => description.
         *
         * 'whatsapp' => 'Connect a WhatsApp number (Meta embedded signup)',
         */
        'purposes'                 => [],
    ],
];
