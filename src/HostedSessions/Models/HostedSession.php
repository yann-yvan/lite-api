<?php

namespace Nycorp\LiteApi\HostedSessions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Nycorp\LiteApi\HostedSessions\Events\HostedSessionCompleted;
use Nycorp\LiteApi\HostedSessions\Events\HostedSessionFailed;

/**
 * @property int         $id
 * @property string      $uuid
 * @property string      $purpose
 * @property string      $token_hash
 * @property string      $status
 * @property array|null  $context
 * @property array|null  $result
 * @property string|null $error
 * @property string|null $return_url
 * @property string|null $locale
 * @property Carbon      $expires_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $finished_at
 */
class HostedSession extends Model
{
    public const PENDING   = 'pending';
    public const OPENED    = 'opened';
    public const COMPLETED = 'completed';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';
    // Never stored: a pending or opened session past its expiry
    public const EXPIRED   = 'expired';

    public const TOKEN_PREFIX = 'hs_';

    protected $fillable = ['purpose', 'context', 'return_url', 'locale', 'expires_at'];

    protected $attributes = ['status' => self::PENDING];

    protected $casts = [
        'context'     => 'array',
        'result'      => 'array',
        'expires_at'  => 'datetime',
        'opened_at'   => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $session) {
            $session->uuid ??= (string)Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public static function newToken(): string
    {
        return self::TOKEN_PREFIX . Str::random(48);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The session a token designates, whatever its state
     */
    public static function findByToken(string $token): ?self
    {
        return str_starts_with($token, self::TOKEN_PREFIX) ? self::query()->where('token_hash', self::hash($token))->first() : null;
    }

    public function isExpired(): bool
    {
        return in_array($this->getRawOriginal('status') ?? $this->status, [self::PENDING, self::OPENED], true) && $this->expires_at->isPast();
    }

    /**
     * Status as the API shows it: expired when still open past its expiry
     */
    public function state(): string
    {
        return $this->isExpired() ? self::EXPIRED : $this->status;
    }

    /**
     * The page can still be used: neither finished nor expired
     */
    public function isUsable(): bool
    {
        return in_array($this->state(), [self::PENDING, self::OPENED], true);
    }

    public function markOpened(): void
    {
        if ($this->status === self::PENDING) {
            $this->forceFill(['status' => self::OPENED, 'opened_at' => now()])->save();
        }
    }

    public function complete(array $result = []): void
    {
        $this->forceFill(['status' => self::COMPLETED, 'result' => $result, 'error' => null, 'finished_at' => now()])->save();

        event(new HostedSessionCompleted($this));
    }

    public function fail(string $error, array $result = []): void
    {
        $this->forceFill(['status' => self::FAILED, 'result' => $result ?: null, 'error' => mb_substr($error, 0, 1000), 'finished_at' => now()])->save();

        event(new HostedSessionFailed($this));
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => self::CANCELLED, 'finished_at' => now()])->save();
    }

    /**
     * Where the user goes back after the page: return_url with the session id and its state
     */
    public function returnUrl(): ?string
    {
        if (empty($this->return_url)) {
            return null;
        }

        $query = http_build_query(['session_id' => $this->uuid, 'status' => $this->state()]);

        return $this->return_url . (str_contains($this->return_url, '?') ? '&' : '?') . $query;
    }

    /**
     * API representation (the token is never part of it)
     */
    public function present(): array
    {
        return [
            'id'          => $this->uuid,
            'purpose'     => $this->purpose,
            'status'      => $this->state(),
            'context'     => $this->context ?? (object)[],
            'result'      => $this->result,
            'error'       => $this->error,
            'return_url'  => $this->return_url,
            'locale'      => $this->locale,
            'expires_at'  => $this->expires_at?->toIso8601String(),
            'opened_at'   => $this->opened_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
