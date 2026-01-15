<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

use cryodrift\fw\Context;

interface Configs
{
    public static function addConfigs(Context $ctx, array $data, string $typ = ''): void;
}
