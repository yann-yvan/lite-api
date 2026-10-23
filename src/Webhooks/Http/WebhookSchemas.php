<?php

namespace Nycorp\LiteApi\Webhooks\Http;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema    : 'WebhookEndpointInput',
    properties: [
        new OA\Property(property: 'url', description: 'Public HTTPS URL', type: 'string', format: 'uri', example: 'https://example.com/webhooks/shortext'),
        new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string'), example: ['message.*', 'conversation.handover']),
        new OA\Property(property: 'filters', description: 'Only receive events whose context matches, e.g. one agent', type: 'object', example: ['app' => 'AGI-XXXXX'], nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_enabled', type: 'boolean'),
    ],
    type      : 'object'
)]
#[OA\Schema(
    schema    : 'WebhookEndpoint',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'url', type: 'string', format: 'uri'),
        new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'filters', type: 'object', nullable: true),
        new OA\Property(property: 'api_version', description: 'Version of the payloads sent to this endpoint', type: 'string', example: 'v2.0', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_enabled', type: 'boolean'),
        new OA\Property(property: 'disabled_reason', description: 'Set when disabled after failing for 3 days', type: 'string', nullable: true),
        new OA\Property(property: 'failing_since', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_success_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_failure_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_error', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type      : 'object'
)]
#[OA\Schema(
    schema    : 'WebhookDelivery',
    properties: [
        new OA\Property(property: 'id', description: 'Sent in the Delivery header', type: 'string', format: 'uuid'),
        new OA\Property(property: 'event_id', type: 'string', example: 'evt_01JB...'),
        new OA\Property(property: 'event_type', type: 'string', example: 'message.received'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'succeeded', 'failed']),
        new OA\Property(property: 'attempts', type: 'integer'),
        new OA\Property(property: 'response_status', type: 'integer', nullable: true),
        new OA\Property(property: 'response_body', description: 'First 2000 characters', type: 'string', nullable: true),
        new OA\Property(property: 'duration_ms', type: 'integer', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(property: 'next_retry_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'payload', ref: '#/components/schemas/WebhookEventPayload'),
    ],
    type      : 'object'
)]
#[OA\Schema(
    schema     : 'WebhookEventPayload',
    description: <<<'MD'
        Body POSTed to your endpoint. Answer with a 2xx within 10 s and process asynchronously.
        Failed deliveries are retried after 10 s, 1 min, 5 min, 30 min, 2 h, 6 h and 12 h;
        an endpoint failing for 3 days is disabled.
        Delivery order is not guaranteed and an event may be delivered twice: dedupe on `id`.

        **Signature**: `X-…-Signature: sha256=hex(hmac_sha256(secret, "{X-…-Timestamp}.{raw body}"))`.
        Compare in constant time and reject timestamps older than 5 minutes.
        MD,
    properties : [
        new OA\Property(property: 'id', description: 'Event id, identical on redeliveries', type: 'string', example: 'evt_01JB...'),
        new OA\Property(property: 'type', type: 'string', example: 'message.received'),
        new OA\Property(property: 'api_version', type: 'string', example: 'v2.0', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'data', description: 'Depends on the event type', type: 'object'),
    ],
    type       : 'object'
)]
class WebhookSchemas
{
}
