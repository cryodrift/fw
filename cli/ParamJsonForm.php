<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Param;

class ParamJsonForm extends ParamJson
{

    public function raw(): array
    {
        return Core::getValue('form', Core::pop(Core::jsonRead($this->data)), []);
    }
}
