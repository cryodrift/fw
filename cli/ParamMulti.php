<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use ArrayObject;
use cryodrift\fw\interface\Param;

class ParamMulti extends ArrayObject implements \Stringable, Param
{

    public function __construct(Context $ctx, public string $name, public string $value)
    {
        if ($value) {
            $data = explode('|', $value);
        } else {
            $data = [];
        }
        parent::__construct($data, ArrayObject::ARRAY_AS_PROPS);
    }

    public function __toString(): string
    {
        return Core::jsonWrite($this->getArrayCopy());
    }

}
