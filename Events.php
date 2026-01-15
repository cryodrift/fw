<?php

//declare(strict_types=1);

namespace cryodrift\fw;

/**
 * @interface for methods methodname(array $eventdata,...$otherParams)
 * \cryodrift\fw\Events::addConfig($cfg, 'eventname', 'handlerclass', 'method', ['param' => '']);
 *
 */
class Events
{
    private static ?Events $instance = null;

    /**
     * @param array $listeners
     */
    public function __construct(protected array $listeners, protected Context $ctx)
    {
    }


    public function add(string $name, callable $listener): void
    {
        $this->listeners[$name][] = $listener;
    }

    public function run(string $name, mixed $data): void
    {
        foreach (Core::getValue($name, $this->listeners, []) as $listener) {
            $method = $listener['method'];
            $params = $listener['params'];
            $obj = Core::newObject($listener['classname'], $this->ctx);
            $params['eventdata'] = $data;
            $obj->$method(...Core::getParams($obj, $method, $params, $this->ctx));
        }
    }

    public static function addConfig(Config $cfg, string $name, string $classname, string $method, array $params = [])
    {
        $cfg[self::class]['listeners'][$name][] = ['classname' => $classname, 'method' => $method, 'params' => $params];
    }

    public static function getInstance(Context $ctx): Events
    {
        if (self::$instance === null) {
            self::$instance = Core::newObject(self::class, $ctx);
        }
        return self::$instance;
    }

    // Prevent cloning
    private function __clone()
    {
    }

    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

}
