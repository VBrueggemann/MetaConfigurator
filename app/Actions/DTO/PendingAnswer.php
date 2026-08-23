<?php

namespace App\Actions\DTO;

final class PendingAnswer
{
    public function __construct(
        public readonly string $viewId,
        public readonly bool|int|string|null $value,
    ) {}
}
