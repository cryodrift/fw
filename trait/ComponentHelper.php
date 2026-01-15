<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;

/**
 * @date 2025/4
 * @why new naming makes it more natural to use the trait method
 */
trait ComponentHelper
{
    protected array $componentscache = [];
    protected string $componentprefix = 'api_';

    protected function componentHelper(Context $ctx, Config $config): array
    {
        $out = [];
        $ctx = clone $ctx;
        foreach (Core::getValue('components', $config, []) as $component) {
            if (is_string($component)) {
                $component = Config::container($component, Core::getValue('componenthandler', $config), $component);
            }
            list($apiname, $classname, $method) = $component;
            $this->componentscache[$classname] = $obj = Core::getValue($classname, $this->componentscache, Core::newObject($classname, $ctx));
            $apidata = $obj->handleWeb($ctx, $method, $method);
            $out[$this->componentprefix . $apiname] = $apidata->response()->getContent();
        }
        return $out;
    }
}
