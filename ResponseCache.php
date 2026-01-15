<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\interface\Handler;

class ResponseCache implements Handler
{
    private string $name;


    public function __construct(protected FileCache $cache, protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        if (!Config::isCli()) {
//            Core::$log[] = Core::toLog(__METHOD__, 'start', Core::time());
            $this->cache = Core::newObject(Core::getValue('class', $this->config, 'sys\interface\Cache'), $ctx);
//        Core::echo(__METHOD__, $this->config);
            $this->name = Core::toLog($ctx->request()->vars());
            $this->cache->setName($ctx->request()->path()->getString() . '/');

            if ($ctx->request()->isPost()) {
                $this->cache->clear();
            }
            // check if url is in cache
            if ($this->cache->has($this->getKey())) {
                // get data from cache and stop all further handlers
                $ctx->response($this->load());
                $ctx->response()->status(Response::STATUS_FINAL);
            } else {
                $ctx->response()->addAfterRunner(function (Response $response) {
                    $this->save($response);
                });
            }
//            Core::$log[] = Core::toLog(__METHOD__, 'end', Core::time());
        }
        return $ctx;
    }

    private function save(Response $response): void
    {
        $this->cache->set($this->getKey(), serialize($response));
    }

    private function load(): Response
    {
        return unserialize($this->cache->get($this->getKey()));
    }

    private function getKey(): string
    {
        return md5($this->name) . '.ser';
    }

}
