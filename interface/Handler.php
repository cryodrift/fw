<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

use cryodrift\fw\Context;

interface Handler
{

    public function handle(Context $ctx): Context;
}
