<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use ArrayObject;


class Config extends ArrayObject implements \ArrayAccess
{
    public static string $php_sapi = PHP_SAPI;
    public static string $datadir = '.cryodrift/';
    public static string $webconfig = 'config/config-cache-web.php';
    public static string $cliconfig = 'config/config-cache-cli.php';
    public static string $baseconfig = 'tool/base-config.php';
    public static string $logdir = 'logs/';
    public static array $includedirs = [];
    public static array $pharmounts = [];
    public static array $noecho = [];
    public static string $envfile = '.env';


    public function __construct(array $data = [])
    {
        parent::__construct($data, ArrayObject::STD_PROP_LIST);
    }

    public function setConfig(string $key, array $value): void
    {
        $this[$key] = $value;
    }

    /**
     * adds handler before existing handler or first
     */
    public function addHandlerbefore(string $existinghandlerclass, string $class, array $data): void
    {
        $handlers = $this['Handler'];
        $position = array_search($existinghandlerclass, array_keys($handlers));
        if ($position === false) {
            $position = 0;
        }
        $beforeHandler = array_slice($handlers, 0, $position, true);
        $afterHandler = array_slice($handlers, $position, null, true);
        $beforeHandler[$class] = array_merge_recursive(Core::getValue($class, $handlers, []), $data);
        unset($handlers[$class]);
        $this['Handler'] = $beforeHandler + $afterHandler;
    }

    /**
     * @param string $classname
     * @param array $data
     * @return void
     * append handler config at classname
     */
    public function addHandler(string $classname, array $data): void
    {
        $this['Handler'][$classname] = $data;
    }

    /**
     * @param string $classname
     * @return array
     * get handler config by classname
     */
    public function getHandler(string $classname): array
    {
        return Core::getValue($classname, $this['Handler'], []);
    }

    /**
     * @return bool
     */
    public static function isCli(): bool
    {
        return self::$php_sapi === 'cli';
    }

    public static function setSapi(string $php_sapi): void
    {
        $php_sapi = match ($php_sapi) {
            'fpm-fcgi' => 'web',
            'cli-server' => 'web',
            default => $php_sapi
        };
        if (!in_array($php_sapi, ['cli', 'web'])) {
            throw new \BadMethodCallException('Wrong Sapi value:' . $php_sapi);
        }
        self::$php_sapi = $php_sapi;
    }

    public static function container(string $name, string $classname, string $method, array $params = []): array
    {
        return [$name, $classname, $method, $params];
    }

    public function __get($name): mixed
    {
        if ($this->offsetExists($name)) {
            return $this->offsetGet($name);
        }
        throw new \Exception(Core::toLog('Missing config key: ', $name));
    }
}
