<?php

namespace Nycorp\LiteApi\Versioning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Nycorp\LiteApi\Response\Envelopes\LegacyEnvelope;
use Nycorp\LiteApi\Response\Envelopes\ResponseEnvelope;

final class ApiVersion
{
    /**
     * @param class-string<ResponseEnvelope> $envelope
     */
    public function __construct(
        public readonly string           $name,
        public readonly string           $envelope = LegacyEnvelope::class,
        public readonly ?CarbonImmutable $releasedAt = null,
        public readonly ?CarbonImmutable $deprecatedAt = null,
        public readonly ?CarbonImmutable $sunsetAt = null,
        public readonly ?string          $changelog = null,
        public readonly array            $docs = [],
    )
    {
    }

    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            name        : $name,
            envelope    : $config['envelope'] ?? config('lite-api.versioning.default_envelope', LegacyEnvelope::class),
            releasedAt  : self::date($config['released_at'] ?? null),
            deprecatedAt: self::date($config['deprecated_at'] ?? null),
            sunsetAt    : self::date($config['sunset_at'] ?? null),
            changelog   : $config['changelog'] ?? null,
            docs        : $config['docs'] ?? [],
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }

    public function isDeprecated(): bool
    {
        return $this->status() !== ApiVersionStatus::ACTIVE;
    }

    public function status(?CarbonInterface $at = null): ApiVersionStatus
    {
        $at ??= CarbonImmutable::now();

        if ($this->sunsetAt?->lte($at)) {
            return ApiVersionStatus::SUNSET;
        }

        if ($this->deprecatedAt?->lte($at)) {
            return ApiVersionStatus::DEPRECATED;
        }

        return ApiVersionStatus::ACTIVE;
    }

    public function isSunset(): bool
    {
        return $this->status() === ApiVersionStatus::SUNSET;
    }

    public function envelope(): ResponseEnvelope
    {
        return app($this->envelope);
    }

    public function toArray(): array
    {
        return [
            'version'       => $this->name,
            'status'        => $this->status()->value,
            'released_at'   => $this->releasedAt?->toDateString(),
            'deprecated_at' => $this->deprecatedAt?->toDateString(),
            'sunset_at'     => $this->sunsetAt?->toDateString(),
            'changelog'     => $this->changelog,
            'docs'          => isset($this->docs['route']) ? url($this->docs['route']) : null,
        ];
    }
}
