<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

trait PageHandler
{
    use OutHelper;

    protected function handlePage(Context $ctx, Config $config): Context
    {
        $ctx->request()->setDefaultVars(Core::getValue('getvar_defaults', $config, []));
        $ui = HtmlUi::fromFile($config->templatepath);
        $ui->setAttributes(['langcode' => $config->langcode]);
        $ui->setAttributes(['title' => $config->title]);
        $ui->setAttributes(['description' => $config->description]);
        $this->outHelperAttributes([
            'ROUTE' => '/' . $ctx->request()->route(),
            'url' => '?' . http_build_query($ctx->request()->vars())
          ]
        );
        $ctx->response()->setContent($ui);
        return $ctx;
    }

}
