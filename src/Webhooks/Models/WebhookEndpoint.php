<?php

namespace Nycorp\LiteApi\Webhooks\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Nycorp\LiteApi\Webhooks\Events\WebhookEndpointDisabled;

/**
 * @property int         $id
 * @property string      $uuid
 * @property string      $url
 * @property string      $secret
 * @property array       $events
 * @property array|null  $filters
 * @property string|null $api_version
 * @property string|null $description
 * @property bool        $is_enabled
 * @property Carbon|null $failing_since
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property string|null $last_error
 */
class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'url',
        'secret',
        'events',
        'filters',
        'api_version',
        'description',
        'is_enabled',
    ];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret'          => 'encrypted',
        'events'          => 'array',
        'filters'         => 'array',
        'is_enabled'      => 'boolean',
        'failing_since'   => 'datetime',
        'disabled_at'     => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $endpoint) {
            $endpoint->uuid   ??= (string)Str::uuid();
            $endpoint->secret ??= self::newSecret();
        });
    }

    public static function newSecret(): string
    {
        return 'whsec_' . Str::random(40);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * Exact type, "*" or a prefix wildcard such as "message.*"
     */
    public function subscribesTo(string $type): bool
    {
        foreach ($this->events ?? [] as $pattern) {
            if ($pattern === '*' || $pattern === $type || (str_ends_with($pattern, '.*') && str_starts_with($type, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every filter must equal the event context attribute of the same name
     */
    public function matches(array $context): bool
    {
        foreach ($this->filters ?? [] as $key => $value) {
            if (!array_key_exists($key, $context) || (string)$context[$key] !== (string)$value) {
                return false;
            }
        }

        return true;
    }

    public function rotateSecret(): string
    {
        $this->update(['secret' => $secret = self::newSecret()]);

        return $secret;
    }

    public function enable(): void
    {
        $this->forceFill([
            'is_enabled'      => true,
            'failing_since'   => null,
            'disabled_at'     => null,
            'disabled_reason' => null,
        ])->save();
    }

    public function disable(string $reason): void
    {
        $this->forceFill([
            'is_enabled'      => false,
            'disabled_at'     => now(),
            'disabled_reason' => $reason,
        ])->save();

        event(new WebhookEndpointDisabled($this));
    }

    public function recordSuccess(): void
    {
        $this->forceFill([
            'failing_since'   => null,
            'last_success_at' => now(),
        ])->save();
    }

    public function recordFailure(string $error): void
    {
        $this->forceFill([
            'failing_since'   => $this->failing_since ?? now(),
            'last_failure_at' => now(),
            'last_error'      => Str::limit($error, 1000),
        ])->save();

        $hours = (int)config('lite-api.webhooks.disable_after_hours', 72);

        if ($this->is_enabled && $this->failing_since->lte(now()->subHours($hours))) {
            $this->disable("Failing for more than {$hours}h: $error");
        }
    }

    /**
     * Public representation, never the secret
     */
    public function toApiArray(): array
    {
        return [
            'id'              => $this->uuid,
            'url'             => $this->url,
            'events'          => $this->events,
            'filters'         => $this->filters,
            'api_version'     => $this->api_version,
            'description'     => $this->description,
            'is_enabled'      => $this->is_enabled,
            'disabled_reason' => $this->disabled_reason,
            'failing_since'   => $this->failing_since?->toIso8601String(),
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_failure_at' => $this->last_failure_at?->toIso8601String(),
            'last_error'      => $this->last_error,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
