<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Context;

/**
 * Common CLI defaults handling.
 * Extracted to be reused by different CLI handlers.
 */
trait CliDefaults
{

    /**
     * Merge defaults into the current request parameters if not provided.
     * - CLI args take precedence.
     * - Booleans are normalized to 'true'/'false' strings for Request storage.
     */
    protected function defaultsCli(Context $ctx, array $defaults): void
    {
        $req = $ctx->request();
        $params = $req->getParams();
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $params) || $params[$k] === '' || $params[$k] === null) {
                if (is_bool($v)) {
                    $req->setParam($k, $v ? 'true' : 'false');
                } else {
                    if ($v !== null) {
                        $req->setParam($k, (string)$v);
                    }
                }
            }
        }
    }
}
