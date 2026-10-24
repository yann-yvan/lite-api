<?php

namespace Nycorp\LiteApi\HostedSessions\Http;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\HostedSessions\HostedSessions;
use Nycorp\LiteApi\HostedSessions\Models\HostedSession;
use Nycorp\LiteApi\LiteApi;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Nycorp\LiteApi\Docs\LegacyEnvelopeResponse;
use OpenApi\Attributes as OA;

/**
 * Hosted sessions of the authenticated owner. Registered with LiteApi::hostedSessionRoutes().
 */
#[OA\Schema(
    schema     : 'HostedSession',
    description: 'A signed, expiring, single-use link to one of our hosted pages, and its outcome',
    properties : [
        new OA\Property(property: 'id', description: 'Session id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'purpose', type: 'string', example: 'whatsapp'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'opened', 'completed', 'failed', 'cancelled', 'expired']),
        new OA\Property(property: 'url', description: 'Only in the creation response: the page to give to your user. It holds a secret, never log or share it.', type: 'string'),
        new OA\Property(property: 'context', type: 'object'),
        new OA\Property(property: 'result', description: 'What the session produced (completed)', type: 'object', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(property: 'return_url', description: 'Your page the user is sent back to, with ?session_id=...&status=...', type: 'string', nullable: true),
        new OA\Property(property: 'locale', type: 'string', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'opened_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finished_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type      : 'object'
)]
#[OA\Schema(
    schema    : 'HostedSessionInput',
    required  : ['purpose'],
    properties: [
        new OA\Property(property: 'purpose', description: 'GET /hosted-sessions/purposes', type: 'string', example: 'whatsapp'),
        new OA\Property(property: 'context', description: 'Options of the purpose', type: 'object'),
        new OA\Property(property: 'return_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'locale', type: 'string', example: 'fr', nullable: true),
        new OA\Property(property: 'expires_in', description: 'Minutes, default 60', type: 'integer', minimum: 5, maximum: 1440),
    ],
    type      : 'object'
)]
class HostedSessionController
{
    use ApiResponseTrait;

    /** @var array<string, Closure(Request, Model): array> */
    private static array $contextValidators = [];

    /**
     * Validates and normalizes the context of a purpose: return the context to store, throw a LiteResponseException to refuse.
     *
     * HostedSessionController::contextUsing('whatsapp', fn(Request $request, Model $owner) => [...]);
     */
    public static function contextUsing(string $purpose, ?Closure $validator): void
    {
        if ($validator === null) {
            unset(self::$contextValidators[$purpose]);

            return;
        }

        self::$contextValidators[$purpose] = $validator;
    }

    private function owner(Request $request): Model
    {
        return LiteApi::actor($request)?->owner ?? $request->user();
    }

    private function find(Request $request, string $session): ?HostedSession
    {
        $owner = $this->owner($request);

        return HostedSession::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('uuid', $session)
            ->first();
    }

    private function notFound(): JsonResponse
    {
        return self::liteResponse(ResponseCode::REQUEST_NOT_FOUND, message: 'Hosted session not found');
    }

    #[OA\Get(path: '/hosted-sessions/purposes', operationId: 'listHostedSessionPurposes', summary: 'Purposes of the hosted sessions', security: [['bearerAuth' => []]], tags: ['Hosted sessions'], responses: [new LegacyEnvelopeResponse(schema: new OA\Schema(description: 'purpose => description', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string')))])]
    public function purposes(): JsonResponse
    {
        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, (object)HostedSessions::purposes());
    }

    #[OA\Get(path: '/hosted-sessions', operationId: 'listHostedSessions', description: 'Most recent first, 50 per page.', summary: 'List hosted sessions', security: [['bearerAuth' => []]], tags: ['Hosted sessions'], responses: [new LegacyEnvelopeResponse(body: 'HostedSession', paginated: true)])]
    public function index(Request $request): JsonResponse
    {
        $owner    = $this->owner($request);
        $sessions = HostedSession::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->when($request->filled('purpose'), static fn($query) => $query->where('purpose', $request->query('purpose')))
            ->latest('id')
            ->paginate(50);

        $sessions->setCollection($sessions->getCollection()->map(static fn(HostedSession $session) => $session->present()));

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $sessions);
    }

    #[OA\Post(path: '/hosted-sessions', operationId: 'createHostedSession', description: 'Returns the `url` of a page hosted by us, to give to your user (redirect or new window, not an iframe). It expires and serves once. Follow the outcome with `GET /hosted-sessions/{id}` or the webhooks.', summary: 'Create a hosted session', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/HostedSessionInput')), tags: ['Hosted sessions'], responses: [new LegacyEnvelopeResponse(body: 'HostedSession', with: [new OA\Property(property: 'url', description: 'The page to give to your user (holds a secret)', type: 'string')])])]
    public function store(Request $request): JsonResponse
    {
        $https     = config('lite-api.hosted_sessions.require_https_return_url', true);
        $validator = Validator::make($request->all(), [
            'purpose'    => ['required', 'string', Rule::in(array_keys(HostedSessions::purposes()))],
            'context'    => ['sometimes', 'nullable', 'array'],
            'return_url' => ['sometimes', 'nullable', 'string', 'max:2048', $https ? 'url:https' : 'url:http,https'],
            'locale'     => ['sometimes', 'nullable', 'string', 'max:10'],
            'expires_in' => ['sometimes', 'integer', 'min:5', 'max:' . (int)config('lite-api.hosted_sessions.max_ttl_minutes', 1440)],
        ]);
        if ($validator->fails()) {
            return self::liteResponse(ResponseCode::REQUEST_VALIDATION_ERROR, $validator->errors()->messages(), $validator->errors()->first());
        }

        $purpose = (string)$request->input('purpose');
        $owner   = $this->owner($request);

        try {
            $context = isset(self::$contextValidators[$purpose])
                ? (self::$contextValidators[$purpose])($request, $owner)
                : (array)$request->input('context', []);
        } catch (LiteResponseException $exception) {
            return $exception->toResponse();
        }

        [$session, $url] = HostedSessions::start(
            owner     : $owner,
            purpose   : $purpose,
            context   : $context,
            returnUrl : $request->input('return_url'),
            locale    : $request->input('locale'),
            ttlMinutes: $request->has('expires_in') ? $request->integer('expires_in') : null,
            actor     : LiteApi::actor($request)?->user,
        );

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, ['url' => $url] + $session->present());
    }

    #[OA\Get(path: '/hosted-sessions/{id}', operationId: 'getHostedSession', summary: 'Get a hosted session', security: [['bearerAuth' => []]], tags: ['Hosted sessions'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(body: 'HostedSession')])]
    public function show(Request $request, string $session): JsonResponse
    {
        $model = $this->find($request, $session);

        return $model ? self::liteResponse(ResponseCode::REQUEST_SUCCESS, $model->present()) : $this->notFound();
    }

    #[OA\Delete(path: '/hosted-sessions/{id}', operationId: 'cancelHostedSession', description: 'The URL stops working. A finished session cannot be cancelled.', summary: 'Cancel a hosted session', security: [['bearerAuth' => []]], tags: ['Hosted sessions'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new LegacyEnvelopeResponse(body: 'HostedSession')])]
    public function destroy(Request $request, string $session): JsonResponse
    {
        $model = $this->find($request, $session);
        if (!$model) {
            return $this->notFound();
        }

        if (!$model->isUsable()) {
            return self::liteResponse(ResponseCode::REQUEST_NOT_AUTHORIZED, $model->present(), "The session is already {$model->state()}");
        }

        $model->cancel();

        return self::liteResponse(ResponseCode::REQUEST_SUCCESS, $model->present());
    }
}
