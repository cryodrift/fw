<?php

//declare(strict_types=1);

namespace cryodrift\fw\tool;

class Configmeta
{
    public function __construct(public string $name, public string $pathname, public string $url, public string $tmpfile)
    {
    }

}
