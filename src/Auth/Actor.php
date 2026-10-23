<?php

namespace Nycorp\LiteApi\Auth;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Who is calling the API, whatever the client (integrator key, mobile user token...):
 * the owner whose data is reached (company, account...), the person acting if any, and what they may do.
 */
final class Actor
{
    /**
     * @param Closure(string): bool $can
     */
    public function __construct(
        public readonly Model  $owner,
        public readonly ?Model $user,
        private readonly Closure $can,
    )
    {
    }

    public function can(string $permission): bool
    {
        return (bool)($this->can)($permission);
    }
}
