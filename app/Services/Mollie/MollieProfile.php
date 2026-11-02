<?php

namespace App\Services\Mollie;

/**
 * The Mollie website profile an API key belongs to.
 */
class MollieProfile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
