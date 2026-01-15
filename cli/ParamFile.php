<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\Config;
use cryodrift\fw\interface\Param;

class ParamFile implements \Stringable, Param
{

    public readonly string $filename;

    public function __construct(Context $ctx, public string $name, public string $value, bool $ready = false)
    {
        if (Config::isCli() && $ready === false) {
            if ($value === '') {
                $this->filename = '';
                $this->value = $ctx->request()->stdIN();
            } else {
                $this->filename = $value;
                $this->value = Core::fileReadOnce($value);
            }
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function getPath(): string
    {
        $fi = new \SplFileInfo($this->filename);
        return $fi->getPath();
    }

}
