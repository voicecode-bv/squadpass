<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A row of an imported member list that cannot be imported as it is.
 */
class InvalidMemberImportRow extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
