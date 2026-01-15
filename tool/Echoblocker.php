<?php

//declare(strict_types=1);

namespace cryodrift\fw\tool;

use cryodrift\fw\Context;
use cryodrift\fw\interface\Handler;

/**
 * usage:
 * $out->addHandlerbefore(0,\cryodrift\fw\tool\Echoblocker::class, [
 * 'config' => ['sys\Main::path']
 * ]);
 */
class Echoblocker implements Handler
{

    public function __construct(array $config)
    {
        foreach ($config as $value) {
            \cryodrift\fw\Config::$noecho[] = $value;
        }
    }

    public function handle(Context $ctx): Context
    {
        return $ctx;
    }
}
