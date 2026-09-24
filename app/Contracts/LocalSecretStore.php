<?php

namespace App\Contracts;

interface LocalSecretStore
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $path, array $payload): void;

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array;
}
