<?php

namespace cryodrift\fw\cli;

use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Param;
use cryodrift\fw\trait\CliPrompt;

class ParamSure implements Param
{
    use CliPrompt;


    public function __construct(Context $ctx, string $name, public string $value = '')
    {
        if (Config::isCli() && $value === '' && $name) {
            Core::echoOn();
            Core::echo('Are you Sure? (N,y)' . $name . ': ');
            $tmp = $this->cliprompt();
            if (strtolower($tmp) === 'y') {
                $this->value = $name;
            }
            Core::echoReset($ctx);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
