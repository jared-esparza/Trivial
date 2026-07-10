<?php

declare(strict_types=1);

final class ApiResponse
{
    public function __construct(
        public readonly array $payload = [],
        public readonly int $status = 200,
        public readonly array $cookies = [],
    ) {
    }
}
