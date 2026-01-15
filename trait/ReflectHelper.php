<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Core;

trait ReflectHelper
{

    public function reflectHandlers(string $commentvar): array
    {
        $out = [];
        foreach (Core::getMethods($this, \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $comment = $method->getDocComment();
            if ($comment) {
                $info = Core::getValue($commentvar, Core::getDocCommentVars($comment));
                if ($info) {
                    $out[strtolower($method->name)] = $info;
                }
            }
        }
        return $out;
    }

}
