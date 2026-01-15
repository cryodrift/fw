<?php

//declare(strict_types=1);

namespace cryodrift\fw;

class Paraminfo
{
    public function __construct(
      public string $name,
      public mixed $type,
      public string $typestr,
      public bool $buildin,
      public bool $optional,
      public string $classname,
      public string $method,
      public mixed $defaults
    ) {
    }

}
