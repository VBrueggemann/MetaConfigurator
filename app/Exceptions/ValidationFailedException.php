<?php

namespace App\Exceptions;

use RuntimeException;

final class ValidationFailedException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Configuration validation failed.');
    }
}
