<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Param;

class ParamJson implements Param
{
    protected string $data;

    public function __construct(Context $ctx, string $name, string $value)
    {
        if ($value) {
            $this->data = $value;
        } else {
            $this->data = '[[]]';
        }
    }

    public function raw(): array
    {
        return Core::jsonRead($this->data);
    }

    public function column(string $column): array
    {
        return array_column($this->raw(), $column);
    }

    public function multi(array $keys): array
    {
        return Core::extractKeys($this->raw(), $keys);
    }

    public function single(string $key): mixed
    {
        return Core::getValue($key, Core::pop($this->raw()));
    }

    public function __toString(): string
    {
        return $this->data;
    }
}
