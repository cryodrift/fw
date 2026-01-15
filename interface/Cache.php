<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

interface Cache
{

    public function set(string $key, string $value): void;

    public function get(string $key, $default = ''): string;

    public function delete(string $key): bool;

    public function has(string $key): bool;

    public function clear(): bool;

}
