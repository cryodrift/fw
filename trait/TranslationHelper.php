<?php

namespace cryodrift\fw\trait;

use cryodrift\fw\Context;
use cryodrift\fw\Core;

trait TranslationHelper
{
    public function getTranslations(Context $ctx): array
    {
        $lang = $ctx->language();
        $dir = dirname(new \ReflectionClass($this)->getFileName());
        $data = Core::fileInclude($dir . '/' . $lang . '.php', []);
        return $data;
    }
}
