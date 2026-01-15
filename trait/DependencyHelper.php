<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Context;
use cryodrift\fw\Core;

trait DependencyHelper
{
    /**
     *  inject a request var to the class config
     *  for dependency injection into the class constructor
     */
    protected function injectVar(Context $ctx, string $name, string $classname, mixed $default = ''): void
    {
        $c = Core::getConfig($classname, $ctx);
        $c[$name] = $ctx->request()->vars($name, $default);
        $ctx->config()->setConfig($classname, $c);
    }

}
