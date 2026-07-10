<?php

declare(strict_types=1);

final class ApiRequest
{
    public array $headers;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body = [],
        public readonly array $query = [],
        public readonly array $cookies = [],
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
