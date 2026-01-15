<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use Phar;
use Exception;
use cryodrift\fw\cli\Colors;

class Main
{

    public static array $autoloaders = [];
    public static string $rootdir;

    public static function pharmount(): void
    {
        if (Phar::running()) {
            foreach (Config::$pharmounts as $file) {
                Phar::mount($file, $file);
            }
        }
    }

    public static function readConfig(string $php_sapi = PHP_SAPI): Config
    {
        Config::setSapi($php_sapi);
        $core = new Core(new Config([]));
        $ctx = $core->getContext();
        if ($ctx->response()->isRaw($ctx)) {
            self::obstart();
        }

        if (Config::isCli()) {
            $configfile = self::path(Config::$datadir . Config::$cliconfig);
        } else {
            $configfile = self::path(Config::$datadir . Config::$webconfig);
        }
        if (!file_exists($configfile)) {
            $configfile = Config::$baseconfig;
        }
        $config = require $configfile;

        return new Config($config);
    }

    public static function run(Config $config, bool $echo = true): false|string|Response
    {
        $init = Core::time();
        $core = new Core($config);
        $out = '';
        try {
            //
            if ($core->getContext()->response()->isRaw($core->getContext())) {
                self::obstart();
            } else {
                $out .= Core::toLog('init:', Core::time());
            }
            $response = $core->run();
        } catch (\Throwable $ex) {
            Core::echo(__METHOD__, 'Exception:', get_debug_type($ex), $ex->getMessage() . PHP_EOL, $ex->getTraceAsString());
            $response = new Response('')->setStatusValid();
        }

        if ($response->isDebug($core->getContext())) {
            $dstart = Core::time();

            if ($response instanceof Response) {
                $hidekeys = explode(' ', $core->getContext()->request()->param('debughide'));
//                Core::echo(__METHOD__,$hidekeys);
                if (empty($hidekeys)) {
                    $out .= print_r($response, true);
                } else {
                    $out .= print_r(Core::removeKeys($hidekeys, Core::toArray([$response])), true);
                }
            }
            if ($core->getContext()->request()->hasParam('debug3')) {
                $out .= Core::toLog('params', $core->getContext()->request()->getParams());
                $out .= Core::toLog('route', $core->getContext()->request()->route());
                $out .= Core::toLog('path', $core->getContext()->request()->path()->getParts());
                $out .= Core::toLog('vars', $core->getContext()->request()->vars());
            }

            $out .= Core::toLog('debug init:', $init);
            $out .= Core::toLog('debug start:', $dstart);
            $out .= Core::toLog('debug end:', Core::time());
            if (ob_get_level()) {
                $out .= ob_get_clean();
            }
            $out .= implode('', Core::log());
            echo $out;
            return '';
        }

        if ($response->isHandled()) {
            $collecttime = Core::toLog('collect:', Core::time());
            try {
                if ($echo) {
                    $tmp = (string)$response;
                } else {
                    $tmp = $response;
                }
                if (!$core->getContext()->request()->hasParam('debug2')) {
                    $out = $tmp;
                }
                if ($core->getContext()->request()->hasParam('debug3')) {
                    $out .= Core::toLog('params', $core->getContext()->request()->getParams());
                    $out .= Core::toLog('route', $core->getContext()->request()->route());
                    $out .= Core::toLog('path', $core->getContext()->request()->path()->getParts());
                    $out .= Core::toLog('vars', $core->getContext()->request()->vars());
                }
            } catch (\Throwable $ex) {
                Core::echo(__METHOD__, $ex);
            }
            if (!$response->isRaw($core->getContext())) {
                $out .= $collecttime;
                $out .= Core::toLog('render:', Core::time());
                $out .= Core::toLog('all:', Core::time(true));
            }
            if (count(Core::log())) {
                Core::fileWrite(Config::$datadir . Config::$logdir . 'core.log', implode('', Core::log()), FILE_APPEND);
            }
            if ($response->isRaw($core->getContext())) {
                $log = ob_get_contents();
                if ($log) {
                    Core::fileWrite(Config::$datadir . Config::$logdir . 'debug.log', $log, FILE_APPEND);
                    ob_end_clean();
                }
            }
            if ($echo) {
                echo $out;
                return '';
            } else {
                return $out;
            }
        } else {
            $log = ob_get_contents();
            if ($log) {
                Core::fileWrite(Config::$datadir . Config::$logdir . 'debug.log', $log, FILE_APPEND);
                ob_end_clean();
            }
            // need this for php dev server, other servers handle files first
            return false;
        }
    }

    /**
     * map a namespace to a dir
     * @return void
     */
    public static function autoload(string $namespace, string $dir): void
    {
        self::$autoloaders[trim($namespace, '\\')] = $dir;
    }

    public static function autoloader(): void
    {
        static $registerd;
        if ($registerd) {
            return;
        }
        $registerd = true;
        spl_autoload_register(function ($class): bool {
            $pathname = '';
//            echo 'class: ' . $class . PHP_EOL;
//            echo 'dir: ' . __DIR__ . PHP_EOL;
//            echo 'dir: ' . print_r(get_include_path(),1) . PHP_EOL;
            foreach (self::$autoloaders as $namespace => $value) {
                if (str_starts_with($class, $namespace)) {
                    $pathname = str_replace($namespace, $value, $class) . '.php';
                }
            }
            if (!$pathname) {
                $pathname = self::$rootdir . $class . '.php';
            }
            $pathname = str_replace('\\', '/', $pathname);
//            echo 'pathname: ' . $pathname . PHP_EOL;
//            echo 'realpathname: ' . realpath($pathname) . PHP_EOL;

            if ($pathname) {
                try {
                    include_once($pathname);
                    return true;
                } catch (Exception $ex) {
                }
            }
            return false;
        });
    }

    public static function path(string $path, bool $phar = false): string
    {
        if ($path) {
            if (Config::isCli()) {
                Core::echo(__METHOD__, 'path', Colors::get($path, Colors::FG_light_blue));
            }
            // get out of phar and first check filesystem
            $path = str_replace(G_PHARROOT . '/', '', $path);

            if ($phar) {
                $path = G_PHAR . '' . $path;
                if (Config::isCli()) {
                    Core::echo(__METHOD__, 'phar:', $path);
                }
            }
            if (file_exists($path)) {
                if (!str_contains($path, Config::$datadir . 'config-cache-')) {
                    if (Config::isCli()) {
                        Core::log(__METHOD__, 'normal path ', $path);
                    }
                }
                if (Config::isCli()) {
                    Core::echo(__METHOD__ . '_found', Colors::get($path, Colors::FG_green));
                }
                return $path;
            }

            // then check other includes and last phar
            foreach (Config::$includedirs as $include) {
                $pathname = $include . $path;
                try {
                    if (file_exists($pathname)) {
                        if (!str_contains($path, Config::$datadir . 'config-cache-')) {
                            Core::log(__METHOD__, 'include path ', $pathname);
                        }
                        if (Config::isCli()) {
                            Core::echo(__METHOD__ . '_found', Colors::get($pathname, Colors::FG_green));
                        }
                        return $pathname;
                    } else {
                        Core::echo(__METHOD__ . '_search', Colors::get($pathname, Colors::FG_yellow));
                    }
                } catch (\Exception $ex) {
                    Core::echo(__METHOD__, get_called_class(), $include, $pathname, $ex);
                }
            }
            Core::echo(__METHOD__ . '_missed', Colors::get($path, Colors::FG_red));
        } else {
            Core::log(__METHOD__ . '_empty path', $path);
        }

        return '';
    }

    protected static function obstart(): void
    {
        $oldob = '';
        while (ob_get_level()) {
            $oldob .= ob_get_clean();
        }
        ob_start();
        echo $oldob;
    }

}
