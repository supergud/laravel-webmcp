<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule the customer (or the agent acting for them) broke.
 *
 * These carry a message that is safe to show a visitor and safe to hand back
 * to an AI agent: they say what limit was hit, never anything about other
 * customers, internal identifiers or the state of the system.
 */
class ShopException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replacements
     */
    public static function fromKey(string $key, array $replacements = []): self
    {
        return new self(__($key, $replacements));
    }
}
