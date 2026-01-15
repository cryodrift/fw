<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

interface Storage
{
    public function storefile(\SplFileObject $file, bool $skip = true);
}
