<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

use cryodrift\fw\Context;

interface Installable
{
    public function install(Context $ctx): array;
}
