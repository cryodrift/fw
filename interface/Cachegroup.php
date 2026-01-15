<?php

namespace cryodrift\fw\interface;

interface Cachegroup
{
    public function setGroup(string $name, string $key, string $value): void;

    public function getGroup(string $name, string $key, $default = ''): string;

    public function deleteGroup(string $name, string $key): bool;

    public function hasGroup(string $name, string $key): bool;

    public function clearGroup(string $name): bool;

}
