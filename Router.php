<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\interface\Configs;
use cryodrift\fw\interface\Handler;

class Router implements Handler, Configs
{
    const string TYP_CLI = 'cli';
    const string TYP_WEB = 'web';
    const string TYP_EMPTY = 'empty';

    public function __construct(protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        if (count($ctx->request()->path()->getParts())) {
            $cliroutes = Core::getValue(self::TYP_CLI, $this->config);
            $routes = Core::getValue(self::TYP_WEB, $this->config, []);

            foreach (self::pathParts($ctx->request()->path()) as $path) {
                $value = Core::getValue($path, $routes);
                if (!$value && Config::isCli()) {
                    $value = Core::getValue($path, $cliroutes);
                }
                if (!empty($value)) {
                    return $this->run($value, $ctx, $path);
                }
            }
        } elseif (Config::isCli()) {
            $value = Core::getValue(self::TYP_EMPTY, $this->config, []);
            if (!empty($value)) {
                return $this->run($value, $ctx, '');
            }
        }

        $ctx->response()->setStatusInvalid();
        return $ctx;
    }

    public static function pathParts(Path $path)
    {
        $parts = $path->getParts();
        for ($a = count($parts), $b = 0; $a >= $b; $a--) {
            $out = $path->getString('/', 0, $a);
            if (!$out && $a) {
                $out = '/';
            }
            yield $out;
        }
    }

    /**
     * usage see base-config
     */
    public function routes(Context $ctx): Context
    {
        $data = Core::getValue(self::class, $ctx->config('Handler'));
        if (!$ctx->request()->hasParam('verbose')) {
            unset($data['empty']);
            foreach (['cli', 'web'] as $value) {
                $data[$value] = array_keys($data[$value]);
            }
        }
        $ctx->response()->setData(array_merge($ctx->response()->getData(), $data));
        return $ctx;
    }

    public function folder(Context $ctx): Context
    {
        if (Config::isCli()) {
            $ctx->response()->setContent(file_get_contents($ctx->request()->path()->getString()));
        } else {
            $ctx->response()->status(Response::STATUS_INVALID);
        }
        return $ctx;
    }

    public static function addConfig(Context $ctx, string $name, mixed $data, string $typ): void
    {
        if (in_array($typ, [self::TYP_EMPTY, self::TYP_CLI, self::TYP_WEB])) {
            $config = $ctx->config()->getHandler(self::class);
            $config[$typ][$name] = $data;
            $ctx->config()->addHandler(self::class, $config);
        }
    }

    public static function getConfig(Context $ctx, string $name, string $typ): array
    {
        if (in_array($typ, [self::TYP_EMPTY, self::TYP_CLI, self::TYP_WEB])) {
            $config = $ctx->config()->getHandler(self::class);
            return $config[$typ][$name];
        } else {
            return [];
        }
    }

    public static function addConfigs(Context $ctx, array $data, string $typ = ''): void
    {
        foreach ($data as $route => $config) {
            self::addConfig($ctx, $route, $config, $typ);
        }
    }

    public static function remConfig(Context $ctx, string $name, string $typ): void
    {
        if (in_array($typ, [self::TYP_EMPTY, self::TYP_CLI, self::TYP_WEB])) {
            $config = $ctx->config()->getHandler(self::class);
            unset($config[$typ][$name]);
            $ctx->config()->addHandler(self::class, $config);
        }
    }

    /**
     * $value = 'classname'
     * $value = [['classname','methodname']]
     * $value = [['classname','methodname',[methodparams]]]
     * $value = [['classname','methodname',[methodparams],'destroute']]
     */
    protected function run(string|array $value, Context $ctx, string $path): Context
    {
        $ctx->request()->route($path);
        if (is_array($value)) {
            foreach ($value as $config) {
                if (!is_array($config)) {
                    throw new \Exception('Wrong param format in route. ' . Core::toLog($value));
                }
                if (count($config) === 4) {
                    list($classname, $methodname, $commandname, $destroute) = $config;
                    if ($destroute) {
                        $ctx->request()->route($destroute);
                    }
                    $handler = Core::newObject($classname, $ctx);
                    return $handler->handleWeb($ctx, $methodname, $commandname);
                } else {
                    list($classname, $method) = $config;
                    $handler = Core::newObject($classname, $ctx);
                    if ($method) {
                        $params = is_array(Core::pop($config)) ? Core::pop($config) : [];
                        $ctx = $handler->$method(...Core::getParams($handler, $method, $params, $ctx));
                    } else {
                        return $handler->handle($ctx);
                    }
                }
            }
            return $ctx;
        } else {
            $handler = Core::newObject($value, $ctx);
            return $handler->handle($ctx);
        }
    }


}
