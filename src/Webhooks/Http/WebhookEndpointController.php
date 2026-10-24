<?php

namespace Nycorp\LiteApi\Webhooks\Http;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Nycorp\LiteApi\Webhooks\Models\WebhookDelivery;
use Nycorp\LiteApi\Webhooks\Models\WebhookEndpoint;
use Nycorp\LiteApi\Webhooks\Rules\WebhookUrl;
use Nycorp\LiteApi\Webhooks\Webhooks;
use Nycorp\LiteApi\Docs\LegacyEnvelopeResponse;
use OpenApi\Attributes as OA;

/**
 * Webhook endpoints of the authenticated owner. Registered with LiteApi::webhookRoutes().
 */
class WebhookEndpointController
{
    use ApiResponseTrait;

    private static ?Closure $ownerResolver = null;

    /**
     * Owner of the endpoints for the current request; default: the authenticated user (token owner)
     */
    public static function resolveOwnerUsing(?Closure $resolver): void
    {
        self::$ownerResolver = $resolver;
    }

    private function owner(Request $request): Model
    {
        // Same owner as every other resource of the request (LiteApi::resolveActorUsing)
        return self::$ownerResolver ? (self::$ownerResolver)($request) : (LiteApi::actor($request)?->owner ?? $request->user());
    }

    private function find(Request $request, string $endpoint): ?WebhookEndpoint
    {
        $owner = $this->owner($request);

        return WebhookEndpoint::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('uuid', $endpoint)
            ->first();
    }

    private function notFound(): JsonResponse
    {
        return self::liteResponse(ResponseCode::REQUEST_NOT_FOUND, message: 'Webhook endpoint not found');
    }

    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'url'         => [$required, 'string', 'max:2048', new WebhookUrl()],
            'events'      => [$required, 'array', 'min:1'],
            'events.*'    => ['string', static function (string $attribute, mixed $value, Closure $fail) {
                if (!self::isKnownEvent((string)$value)) {
                    $fail("Unknown event $value, see GET webhooks/events");
                }
            }],
            'filters'     => ['sometimes', 'nullable', 'array'],
            'filters.*'   => [static function (string $attribute, mixed $value, Closure $fail) {
                if (!is_scalar($value)) {
                    $fail("The $attribute filter must be a string, a number or a boolean");
                }
            }],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_enabled'  => ['sometimes', 'boolean'],
        ];
    }

    private static function isKnownEvent(string $pattern): bool
    {
        if ($pattern === '*' || array_key_exists($pattern, Webhooks::catalog())) {
            return true;
        }

        if (!str_ends_with($pattern, '.*')) {
            return false;
        }

        $prefix = substr($pattern, 0, -1);
        foreach (array_keys(Webhooks::catalog()) as $type) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function validate(Request $request, bool $creating): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($creating));

        if ($validator->fails()) {
            return self::liteResponse(ResponseCode::REQUEST_VALIDATION_ERROR, $validator->errors()->messages(), $validator->errors()->first());
        }

        return $validator->validated();
    }

    #[OA\Get(path: '/webhooks/events', operationId: 'listWebhookEvents', description: 'Event types an endpoint can subscribe to. Subscribe with an exact type, a prefix wildcard such as `message.*`, or `*`.', summary: 'Webhook event catalog', security: [['bearerAuth' => []]], tags: ['Webhooks'], responses: [new LegacyEnvelopeResponse(schema: new OA\Schema(type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'type', type: 'string'), new OA\Property(property: 'description', type: 'string')], type: 'object')))])]
    public function events(): JsonResponse
    {
        $events = [];
        foreach (Webhooks::catalog() as $type => $description) {
            $events[] = ['type' => $type, 'description' => $description];
        }

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $events);
    }

    #[OA\Get(path: '/webhooks', operationId: 'listWebhooks', summary: 'List webhook endpoints', security: [['bearerAuth' => []]], tags: ['Webhooks'], responses: [new LegacyEnvelopeResponse(body: 'WebhookEndpoint', list: true)])]
    public function index(Request $request): JsonResponse
    {
        $owner = $this->owner($request);

        $endpoints = WebhookEndpoint::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->latest()
            ->get()
            ->map(static fn(WebhookEndpoint $endpoint) => $endpoint->toApiArray());

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $endpoints);
    }

    #[OA\Post(
        path       : '/webhooks',
        operationId: 'createWebhook',
        description: 'The signing `secret` is returned **only once**, keep it. Payloads follow the API version used to create the endpoint.',
        summary    : 'Create a webhook endpoint',
        security   : [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/WebhookEndpointInput')),
        tags       : ['Webhooks'],
        responses  : [new LegacyEnvelopeResponse(body: 'WebhookEndpoint', with: [new OA\Property(property: 'secret', description: 'Signing secret, shown only here', type: 'string')])]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, true);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $endpoint = new WebhookEndpoint($data + ['api_version' => LiteApi::currentVersion()?->name]);
        $endpoint->owner()->associate($this->owner($request));
        $endpoint->save();

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $endpoint->toApiArray() + ['secret' => $endpoint->secret]);
    }

    #[OA\Get(path: '/webhooks/{id}', operationId: 'getWebhook', summary: 'Get a webhook endpoint', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(body: 'WebhookEndpoint')])]
    public function show(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);

        return $model ? self::liteResponse(ResponseCode::REQUEST_SUCCESS, $model->toApiArray()) : $this->notFound();
    }

    #[OA\Put(
        path       : '/webhooks/{id}',
        operationId: 'updateWebhook',
        description: 'Setting `is_enabled` to true re-enables an endpoint disabled after repeated failures.',
        summary    : 'Update a webhook endpoint',
        security   : [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/WebhookEndpointInput')),
        tags       : ['Webhooks'],
        parameters : [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses  : [new LegacyEnvelopeResponse(body: 'WebhookEndpoint')]
    )]
    public function update(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);
        if (!$model) {
            return $this->notFound();
        }

        $data = $this->validate($request, false);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $enable = ($data['is_enabled'] ?? null) === true && !$model->is_enabled;
        $model->update($data);

        if ($enable) {
            $model->enable();
        }

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $model->refresh()->toApiArray());
    }

    #[OA\Delete(path: '/webhooks/{id}', operationId: 'deleteWebhook', summary: 'Delete a webhook endpoint', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(schema: new OA\Schema(type: 'null'), description: 'Deleted')])]
    public function destroy(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);
        if (!$model) {
            return $this->notFound();
        }

        $model->delete();

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS);
    }

    #[OA\Post(path: '/webhooks/{id}/rotate-secret', operationId: 'rotateWebhookSecret', description: 'The previous secret stops working immediately.', summary: 'Rotate the signing secret', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(with: [new OA\Property(property: 'secret', description: 'The new signing secret', type: 'string')])])]
    public function rotateSecret(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);

        return $model ? self::liteResponse(ResponseCode::REQUEST_SUCCESS, ['secret' => $model->rotateSecret()]) : $this->notFound();
    }

    #[OA\Post(path: '/webhooks/{id}/test', operationId: 'testWebhook', description: 'Sends a `webhook.test` event right away (no retry) and returns the delivery with the endpoint response.', summary: 'Send a test event', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(body: 'WebhookDelivery')])]
    public function test(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);

        return $model ? self::liteResponse(ResponseCode::REQUEST_SUCCESS, Webhooks::ping($model)->toApiArray()) : $this->notFound();
    }

    #[OA\Get(path: '/webhooks/{id}/deliveries', operationId: 'listWebhookDeliveries', description: 'Most recent first, 50 per page. Deliveries are kept 30 days.', summary: 'Delivery history', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'succeeded', 'failed'])), new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))], responses: [new LegacyEnvelopeResponse(body: 'WebhookDelivery', paginated: true)])]
    public function deliveries(Request $request, string $endpoint): JsonResponse
    {
        $model = $this->find($request, $endpoint);
        if (!$model) {
            return $this->notFound();
        }

        $status = $request->query('status');

        $page = $model->deliveries()
            ->when(in_array($status, [WebhookDelivery::STATUS_PENDING, WebhookDelivery::STATUS_SUCCEEDED, WebhookDelivery::STATUS_FAILED], true), static fn($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(50);

        $page->setCollection($page->getCollection()->map(static fn(WebhookDelivery $delivery) => $delivery->toApiArray()));

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $page);
    }

    #[OA\Post(path: '/webhooks/{id}/deliveries/{delivery}/retry', operationId: 'retryWebhookDelivery', description: 'Queues a new delivery of the same event (same event `id`).', summary: 'Redeliver an event', security: [['bearerAuth' => []]], tags: ['Webhooks'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')), new OA\Parameter(name: 'delivery', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(body: 'WebhookDelivery', description: 'The new WebhookDelivery')])]
    public function retry(Request $request, string $endpoint, string $delivery): JsonResponse
    {
        $model    = $this->find($request, $endpoint);
        $original = $model?->deliveries()->where('uuid', $delivery)->first();

        if (!$original) {
            return self::liteResponse(ResponseCode::REQUEST_NOT_FOUND, message: 'Webhook delivery not found');
        }

        if (!$model->is_enabled) {
            return self::liteResponse(ResponseCode::REQUEST_NOT_AUTHORIZED, message: 'Enable the endpoint before retrying');
        }

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, Webhooks::redeliver($original)->toApiArray());
    }
}
