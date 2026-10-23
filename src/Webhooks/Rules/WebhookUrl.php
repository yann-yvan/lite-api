<?php

namespace Nycorp\LiteApi\Webhooks\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Nycorp\LiteApi\Webhooks\WebhookUrlGuard;

class WebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reason = is_string($value) ? WebhookUrlGuard::check($value) : 'The URL must be a string';

        if ($reason !== null) {
            $fail($reason);
        }
    }
}
