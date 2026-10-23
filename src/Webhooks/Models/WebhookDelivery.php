<?php

namespace Nycorp\LiteApi\Webhooks\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int                  $id
 * @property string               $uuid
 * @property int                  $webhook_endpoint_id
 * @property string               $event_id
 * @property string               $event_type
 * @property string               $payload
 * @property string               $status
 * @property int                  $attempts
 * @property int|null             $response_status
 * @property string|null          $response_body
 * @property int|null             $duration_ms
 * @property string|null          $error
 * @property Carbon|null          $next_retry_at
 * @property Carbon|null          $delivered_at
 * @property WebhookEndpoint|null $endpoint
 */
class WebhookDelivery extends Model
{
    use Prunable;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
    ];

    protected $attributes = [
        'status'   => self::STATUS_PENDING,
        'attempts' => 0,
    ];

    protected $casts = [
        'attempts'        => 'integer',
        'response_status' => 'integer',
        'duration_ms'     => 'integer',
        'next_retry_at'   => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $delivery) {
            $delivery->uuid ??= (string)Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id')->withTrashed();
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int)config('lite-api.webhooks.retention_days', 30)));
    }

    public function isFinal(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }

    public function recordAttempt(bool $succeeded, ?int $status, ?string $body, ?int $durationMs, ?string $error): void
    {
        $this->forceFill([
            'status'          => $succeeded ? self::STATUS_SUCCEEDED : $this->status,
            'attempts'        => $this->attempts + 1,
            'response_status' => $status,
            'response_body'   => $body === null ? null : Str::limit($body, 2000),
            'duration_ms'     => $durationMs,
            'error'           => $error === null ? null : Str::limit($error, 1000),
            'next_retry_at'   => null,
            'delivered_at'    => $succeeded ? now() : null,
        ])->save();
    }

    public function markFailed(?string $error = null): void
    {
        $this->forceFill([
            'status'        => self::STATUS_FAILED,
            'error'         => $error === null ? $this->error : Str::limit($error, 1000),
            'next_retry_at' => null,
        ])->save();
    }

    public function toApiArray(): array
    {
        return [
            'id'              => $this->uuid,
            'event_id'        => $this->event_id,
            'event_type'      => $this->event_type,
            'status'          => $this->status,
            'attempts'        => $this->attempts,
            'response_status' => $this->response_status,
            'response_body'   => $this->response_body,
            'duration_ms'     => $this->duration_ms,
            'error'           => $this->error,
            'next_retry_at'   => $this->next_retry_at?->toIso8601String(),
            'delivered_at'    => $this->delivered_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'payload'         => json_decode($this->payload, true),
        ];
    }
}
