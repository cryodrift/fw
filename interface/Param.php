<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

use cryodrift\fw\Context;


interface Param
{
    public function __construct(Context $ctx, string $name, string $value);

    public function __toString(): string;
}
