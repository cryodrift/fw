<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\Config;
use cryodrift\fw\interface\Param;
use cryodrift\fw\trait\CliPrompt;

class ParamHidden implements \Stringable, Param
{
    use CliPrompt;

    public function __construct(Context $ctx, public string $name, public mixed $value)
    {
        if (Config::isCli() && $value === '' && $name) {
            Core::echoOn();
            Core::echo('Input ' . $name . ': ');
            $this->value = $this->cliprompt(true);
            Core::echoReset($ctx);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

}
