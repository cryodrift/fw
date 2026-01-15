<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

trait OutHelper
{
    private array $outhelper_attributes = [];

    protected function outHelper(Context|array|string|\Stringable $data, Context $ctx): Context
    {
        if ($data instanceof Context) {
            $ctx = $data;
            if ($ctx->response()->getContent() instanceof HtmlUi) {
                $ctx->response()->getContent()->setAttributes($this->outhelper_attributes);
            }
        } elseif (is_array($data)) {
            $ctx->response()->setData($data);
        } elseif ($data instanceof HtmlUi) {
            // TODO not sure if this is the best place
            $data->setAttributes($this->outhelper_attributes);
            $ctx->response()->setContent($data);
        } else {
            $ctx->response()->setContent($data);
        }
        return $ctx;
    }

    protected function outHelperAttributes(array $attributes):self
    {
        $this->outhelper_attributes = array_merge($this->outhelper_attributes, $attributes);
        return $this;
    }
}
