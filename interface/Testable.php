<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

use cryodrift\fw\Context;

interface Testable
{
    public function test(Context $ctx): array;
}
