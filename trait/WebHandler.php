<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

trait WebHandler
{
    use ReflectHelper;
    use OutHelper;

    protected array $urlparts = ['_', 'method', 'command'];
    protected string $docvar = 'web';
    public string $methodname = 'method';
    protected string $commandname = 'command';
    protected string $defaultmethod = 'index';

    public function handleWeb(Context $ctx, string $methodname = '', string $commandname = ''): Context
    {
        $path = $ctx->request()->path()->nameParts(...$this->urlparts);
        if (!$methodname) {
            $methodname = $path->getByName($this->methodname, $this->defaultmethod);
        }
        if (!$commandname) {
            $commandname = $path->getByName($this->commandname);
        }
        $methods = $this->reflectHandlers($this->docvar);
        if (array_key_exists($methodname, $methods)) {
            $params = Core::getParams($this, $methodname, [...$ctx->request()->vars(), $this->commandname => $commandname], $ctx);
            $out = $this->$methodname(...$params);
        } else {
            $out = HtmlUi::fromString('');
        }

        return $this->outHelper($out, $ctx);
    }

}
