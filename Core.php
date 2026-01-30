<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\interface\Params;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveFilterIterator;
use RecursiveCallbackFilterIterator;
use RecursiveArrayIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use cryodrift\fw\cli\CliUi;
use cryodrift\fw\interface\Param;

/**
 *
 */
class Core
{
    /**
     * @var Context
     */
    protected Context $ctx;

    public static array $log = [];

    public static array $filecache = [];

    public static string $echotemp = '';

    /**
     * @var array|null
     */
    protected static null|array $env = null;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        self::$echotemp = '';
        $this->ctx = self::newContext($config);
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            self::$log[] = Core::toLog('Core::errorhandler', $errno, $errstr, $errfile, $errline);
            if (E_RECOVERABLE_ERROR === $errno) {
                throw new \BadMethodCallException($errstr);
            }
        }, E_ALL);
    }

    public static function newContext(Config $config): Context
    {
        return new Context($config, $_SERVER, new Response(''), new Request());
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->ctx;
    }

    /**
     * @return bool
     */
    public static function isUnix(): bool
    {
        return !(DIRECTORY_SEPARATOR === '\\');
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function run(): Response
    {
        $ctx = clone $this->ctx;
        // run Input handler
        foreach ($this->ctx->config('Handler') as $classname => $config) {
            if (is_string($config)) {
                $config = $this->ctx->config($config);
            }
            $instance = self::newObject($classname, $ctx, $config);
            $ctx = $instance->handle($ctx);
            if ($ctx->response()->isFinal()) {
                break;
            }
        }
        return $ctx->response();
    }

    /**
     * @param Context $ctx
     * @param array $config
     * @template T of object
     * @param class-string<T> $classname
     * @return T
     */
    public static function newObject(string $classname, Context $ctx, array $config = []): object
    {
        $params = [];
        try {
            $params = self::getParams($classname, '__construct', $config, $ctx);
        } catch (\ReflectionException $ex) {
//            Core::echo(__METHOD__, $ex);
        }
        if ($classname == 'string') {
            Core::echo(__METHOD__, $classname, $params);
            die('getParams still not working and returning string as classname');
        }
        return new $classname(...$params);
    }

    /**
     * get all parameters for a method
     */
    public static function getParams(object|string $instance, string $methodname, array $source, Context $ctx, bool $ctxclone = true): array
    {
        $params = [];
        foreach (self::methodParams($instance, $methodname) as $paraminfo) {
            $value = self::getParamValue($paraminfo, $source, $ctx, $ctxclone);
            if (array_key_exists($paraminfo->name, $value)) {
                $params[$paraminfo->name] = $value[$paraminfo->name];
            }
        }
        return $params;
    }

    public static function getParamValue(Paraminfo $paraminfo, array $source, Context $ctx, bool $ctxclone = true): array
    {
        $name = $paraminfo->name;
        $type = $paraminfo->type;
        $builtin = $paraminfo->buildin;
        $optional = $paraminfo->optional;
        $classname = $paraminfo->classname;
        $method = $paraminfo->method;
        $params = [];
        $typeinfo = null;
//        Core::echo(__METHOD__, $paraminfo, $source);
        try {
            if (is_string($type) && $builtin === false) {
                $typeinfo = new \ReflectionClass($type);
            }
        } catch (\Exception $ex) {
        }
        $nameconfig = Core::getValue($name, self::getConfig($classname, $ctx, $optional), null);
        switch (true) {
            case $builtin:
                $p = Core::getValue($name, $source, $nameconfig);
                if ($p !== null) {
                    if ($type !== 'mixed') {
                        $p = Core::castValue($p, $type);
                    }
                    $params[$name] = $p;
                } elseif (!$optional) {
                    throw new \BadMethodCallException('Missing (' . $paraminfo->typestr . ') value for ' . $classname . '->' . $method . '(' . $name . ')');
                }
                break;
            case $type === Context::class:
                if ($ctxclone) {
                    $params[$name] = clone $ctx;
                } else {
                    $params[$name] = $ctx;
                }
                break;
            case $type === Config::class:
                if (count($source)) {
                    $params[$name] = new Config($source);
                } else {
                    $params[$name] = new Config(self::getConfig($classname, $ctx, $optional));
                }
                break;
            case self::hasInterface($type, Params::class):
            case self::hasInterface($type, Param::class):
                if (array_key_exists($name, $source)) {
                    $params[$name] = new $type($ctx, $name, Core::getValue($name, $source, '', true));
                } elseif (!$optional) {
                    throw new \BadMethodCallException('Missing (' . $paraminfo->typestr . ') value for ' . $classname . '->' . $method . '(' . $name . ')');
                }
                break;
            case $typeinfo && ($typeinfo->isAbstract() || $typeinfo->isInterface()):
                if (!$nameconfig) {
                    throw new \Exception(Core::toLog(__METHOD__, 'CAN´T INSTANCIATE THE ABSTRACT OR INTERFACE !!', $typeinfo, $name, $classname, $optional));
                } else {
                    $params[$name] = self::newObject($nameconfig, $ctx);
                }
                break;
            default:
                // warning: do not add $source here it leads to param injections
                $params[$name] = self::newObject($type, $ctx);
        }

        return $params;
    }

    public static function methodParams(object|string $instance, string $methodname): iterable
    {
        foreach (new \ReflectionMethod($instance, $methodname)->getParameters() as $key => $param) {
            $name = $param->getName();
            $types = $param->getType();
            $defaults = '';
            if ($types instanceof \ReflectionUnionType) {
                $typestr = implode('|', $types->getTypes());
                // TODO implement this
//                Core::echo(__METHOD__, $types);
                $type = 'mixed';
                $builtin = true;
            } else {
                $type = $param->getType()->getName();
                $typestr = $type;
                $builtin = $param->getType()->isBuiltin();
            }

            $type = match ($type) {
                'bool' => true,
                'int' => 1,
                'array' => [],
                default => $type
            };
            if ($param->isDefaultValueAvailable()) {
                $defaults = $param->getDefaultValue();
            }
            yield new Paraminfo(
              $name, $type, $typestr, $builtin, $param->isOptional(), is_string($instance) ? $instance : get_class($instance),
              $methodname, $defaults
            );
        }
    }

    /**
     * @param string $name
     * @param Context $ctx
     * @param bool $optional
     * @return array
     * @throws \Exception
     */
    public static function getConfig(string $name, Context $ctx, bool $optional = false): array
    {
        if ($optional) {
            $config = Core::getValue($name, $ctx->config(), Core::getValue($name, $ctx->config('Handler'), []));
        } else {
            try {
                $config = $ctx->config($name);
            } catch (\Exception $exception) {
                $config = Core::getValue($name, $ctx->config('Handler'), []);
            }
        }
        if (is_array($config)) {
            return $config;
        }
        return self::getConfig($config, $ctx, $optional);
    }

    /**
     * @param string|int|float|bool $key
     * @param mixed $data
     * @param mixed $default can be Closure
     * @param bool $defaultIfempty
     * @return mixed|string
     */
    public static function getValue(string|int|float|bool $key, mixed $data, mixed $default = '', bool $defaultIfempty = false): mixed
    {
        if ($data instanceof Config) {
            $data = $data->getArrayCopy();
        }
        if (is_array($data) && array_key_exists($key, $data)) {
            if ($defaultIfempty) {
                if (empty($data[$key]) && $data[$key] != 0) {
                    if ($default instanceof \Closure) {
                        $default = $default();
                    }
                    return $default;
                }
            }
            return $data[$key];
        } else {
            if ($default instanceof \Closure) {
                $default = $default();
            }
            return $default;
        }
    }

    /**
     * @param array $parts
     * @param int $pos
     * @param mixed|null $default
     * @param int $length
     */
    public static function getPart(array $parts, int $pos, mixed $default = null, int $length = 1): mixed
    {
        $found = array_slice($parts, $pos, $length);
        if (array_key_exists(0, $found)) {
            return $found[0];
        } else {
            return $default;
        }
    }

    /**
     * recursive get key value from array by searching for key
     * this is not fully recursive we need at least one key in the first level to go deeper
     * recursive means to dive into numeric arrays
     * @param array $data
     * @param array $keys
     * @return array
     */
    public static function extractData(array $data, array $keys, bool $recursiv = true): array
    {
        $out = [];
        foreach ($keys as $key) {
            $val = self::getValue($key, $data);
            if ($val || is_null(self::getValue($key, $data, null, true))) {
                if (is_array($val) && $recursiv) {
                    $first = array_key_first($val);
                    if (is_numeric($first) && !is_array($val[$first])) {
                        $out[$key] = $val;
                    } else {
                        $found = self::extractData($val, $keys, $recursiv);
                        if ($found) {
                            $out[$key] = $found;
                        }
                    }
                } else {
                    if ($val) {
                        $out[$key] = $val;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @param array $keynames
     * @param array $data
     * @return array
     */
    public static function removeKeys(array $keynames, array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $keynames)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $value = self::removeKeys($keynames, $value);
            }
        }
        return $data;
    }

    /**
     */
    public static function extractKeys(array $data, array $keynames, bool $collect = false): array
    {
        $out = [];
        if (empty($keynames)) {
            foreach ($data as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $out = array_merge($out, self::extractKeys($value, $keynames, $collect));
                } else {
                    if ($collect) {
                        $out[] = [$key => $value];
                    } else {
                        $out[$key] = $value;
                    }
                }
            }
        } else {
            if (is_numeric(array_key_first($keynames))) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $keynames)) {
                        if ($collect) {
                            $out[] = [$key => $value];
                        } else {
                            $out[$key] = $value;
                        }
                    } elseif (is_array($value)) {
                        $out = array_merge($out, self::extractKeys($value, $keynames, $collect));
                    }
                }
            } else {
                //map mode
                $search = array_values($keynames);
                $dest = array_keys($keynames);
                foreach ($data as $key => $value) {
                    if (in_array($key, $search)) {
                        $k = array_search($key, $search);
                        if ($collect) {
                            $out[] = [$dest[$k] => $value];
                        } else {
                            $out[$dest[$k]] = $value;
                        }
                    } elseif (is_array($value)) {
                        $out = array_merge($out, self::extractKeys($value, $keynames, $collect));
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @param array $data
     * @param callable $fnk
     */
    public static function addData(array $data, callable $fnk): mixed
    {
        if (!is_numeric(array_key_first($data)) && !empty($data)) {
            $modifieddata = $fnk($data);
            if (!$modifieddata) {
                throw new \Exception(Core::toLog(__METHOD__, 'function must return data!', $data, $modifieddata));
            } else {
                $data = $modifieddata;
            }
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::addData($v, $fnk);
            }
        }
        return $data;
    }

    /**
     * @param array|string $data
     * @param array $excludes
     */
    public static function cleanData(array|string $data, array $excludes = [], bool $urls = false): array|string
    {
        if ($urls) {
            if (is_string($data)) {
                return urlencode($data);
            }

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::cleanData($value, $excludes, $urls);
                } else {
                    if (!in_array($key, $excludes)) {
                        if ($value && is_string($value)) {
                            $data[$key] = urlencode($value);
                        }
                    }
                }
            }
        } else {
            if (is_string($data)) {
                return htmlspecialchars($data);
            }

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::cleanData($value, $excludes, $urls);
                } else {
                    if (!in_array($key, $excludes)) {
                        if ($value && is_string($value)) {
                            $data[$key] = htmlspecialchars($value);
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param object|string $objectOrClass
     * @param int|null $type
     * @return \ReflectionMethod[]
     * @throws \ReflectionException
     */
    public static function getMethods(object|string $objectOrClass, int|null $type = null): array
    {
        $obj = new \ReflectionClass($objectOrClass);
        return $obj->getMethods($type);
    }

    /**
     * @param string $comment
     * @return array
     */
    public static function getDocCommentVars(string $comment): array
    {
        $out = [];
        if (preg_match_all('/@(\w+)\ *(.*)/', $comment, $matches)) {
            foreach ($matches[1] as $key => $match) {
                if (!array_key_exists($match, $out)) {
                    $out[$match] = [];
                }
                $out[$match][] = $matches[2][$key];
            }
            return $out;
        } else {
            return array();
        }
    }

    /**
     * @param $path
     * @param callable $filter fn(SplFileInfo):bool
     * @return RecursiveIteratorIterator
     * directory listing , iterates recursive over any dir structure
     * use eg. filter fn(SplFileInfo $file) => $file->isDir() || $file->getBasename() === 'config.php'
     */
    public static function dirList(
      string|iterable $pathoriterator,
      callable|null $filter = null,
      int $skip = FilesystemIterator::SKIP_DOTS
    ): RecursiveIteratorIterator {
        if (is_string($pathoriterator)) {
            /* normalize path */
            $pathoriterator = Main::path($pathoriterator);

            /* empty/invalid path => empty iterator */
            if (!$pathoriterator) {
                return new RecursiveIteratorIterator(new RecursiveArrayIterator());
            }

            /* build directory iterator */
            $iterator = new RecursiveDirectoryIterator($pathoriterator, $skip);
        } else {
            /* use provided iterator */
            $iterator = $pathoriterator;
        }

        /* create the traversal iterator first so we can read getDepth() in the callback */
        $rii = new RecursiveIteratorIterator(
          new RecursiveCallbackFilterIterator(
            $iterator,
            /* add depth as 2nd arg (keeps old behavior: existing callbacks that accept 1 arg still work) */
            function (mixed $current, mixed $key, RecursiveIterator $it) use (&$rii, $filter): bool {
                /* no filter => accept all */
                if (!$filter) {
                    return true;
                }

                /* depth is only available on RecursiveIteratorIterator */
                $depth = $rii->getDepth();

                /* call with depth; extra args are ignored if callback only defines 1 param */
                return $filter($current, $depth);
            }
          ),
          RecursiveIteratorIterator::SELF_FIRST
        );

        return $rii;
    }


    /**
     * @param mixed ...$arguments
     */
    public static function toLog(mixed ...$arguments): string
    {
        $out = '';
        foreach ($arguments as $argument) {
            if (is_string($argument) || is_bool($argument) || is_numeric($argument) || is_null($argument)) {
                $out .= $argument . ' ';
            } else {
                if ($argument instanceof \Exception) {
                    $out .= self::toLog($argument->getMessage(), $argument->getCode(), $argument->getFile(), $argument->getLine());
                } else {
                    $out .= print_r($argument, true) . PHP_EOL;
                }
            }
        }
        return $out . PHP_EOL;
    }

    /**
     * @return string
     */
    public static function time(bool $sum = false): string
    {
        static $lasttime;
        if (empty($lasttime)) {
            $lasttime = $_SERVER['REQUEST_TIME_FLOAT'];
        }
        $curTime = microtime(true);
        if ($sum) {
            $executionTime = $curTime - $_SERVER['REQUEST_TIME_FLOAT'];
        } else {
            $executionTime = $curTime - $lasttime;
        }
        $lasttime = $curTime;
        return number_format($executionTime, 6) . ' seconds';
    }


    /**
     * @param mixed $variable
     * @param mixed $targetType
     * @return mixed
     */
    public static function castValue(mixed $variable, mixed $targetType): mixed
    {
        // Get the current type of the variable
        $currentType = gettype($variable);
        $nextType = gettype($targetType);
        // Cast the variable to the target type if possible
        switch ($nextType) {
            case 'boolean':
                if ($variable === 'true') {
                    $variable = true;
                } elseif ($variable === 'false') {
                    $variable = false;
                } else {
                    $variable = (bool)$variable;
                }
                break;
            case 'integer':
                $variable = (int)$variable;
                break;
            case 'double':
                $variable = (float)$variable;
                break;
            case 'string':
                $variable = (string)$variable;
                break;
            case 'array':
                $variable = (array)$variable;
                break;
            case 'object':
                $variable = (object)$variable;
                break;
            case 'NULL':
                $variable = null;
                break;
            default:
                throw new \TypeError(Core::toLog($variable, $targetType, $currentType, $nextType));
        }
        // Return the casted variable
        return $variable;
    }

    /**
     * @param mixed ...$args
     * @return void
     */
    public static function echo(mixed ...$args): void
    {
        $method = $args[0];
        if (!in_array($method, Config::$noecho)) {
            CliUi::echoLine(Core::toLog(...$args));
        }
    }

    /**
     * @param mixed ...$args
     * @return void
     */
    public static function echoTmp(mixed ...$args): void
    {
        CliUi::echoTmpMsg(Core::toLog(...$args));
    }

    public static function echoOn(): void
    {
        while (ob_get_level()) {
            self::$echotemp .= ob_get_clean();
        }
    }

    public static function echoReset(Context $ctx): void
    {
        if ($ctx->response()->isRaw($ctx)) {
            ob_start();
            echo self::$echotemp;
            self::$echotemp = '';
        }
    }

    /**
     * @param array|null $data
     * @return mixed
     */
    public static function pop(array|null $data): mixed
    {
        if (is_array($data)) {
            if (is_numeric(array_key_first($data))) {
                return array_pop($data);
            }
        }
        return $data;
    }


    /**
     * @param string $name
     * @param string $default
     */
    public static function env(string $name, string $default = ''): string
    {
        if (file_exists(Config::$envfile)) {
            if (is_null(self::$env)) {
                self::$env = parse_ini_file(Config::$envfile);
            }
        }
        return Core::getValue($name, self::$env, Core::getValue($name, $_ENV, $default));
    }

    /**
     * Reads a local file or remote URL once and caches its content for subsequent calls.
     *
     * Behavior
     * - Uses PHP stream_context options to customize the request when reading from streams/URLs.
     * - The content is cached in-memory by pathname; set $refresh = true to force re-read.
     * - Throws an Exception if $pathname is empty or the content cannot be read.
     *
     * Parameters
     * - $pathname: Local path (e.g. C:\path\file.txt) or URL (e.g. https://example.com/data.json)
     * - $refresh: If true, bypasses cache and re-fetches the content
     * - $streamparams: Stream context options passed to stream_context_create().
     *   Common options you can pass:
     *   http => [
     *     'method'            => 'GET'|'POST'|'PUT'|'DELETE',
     *     'header'            => "Header-Name: value\r\nAnother: value\r\n", // or implode("\r\n", $headers)."\r\n"
     *     'content'           => string,  // request body for POST/PUT
     *     'timeout'           => int|float, // seconds
     *     'ignore_errors'     => true, // keep body on 4xx/5xx (default true here)
     *     'user_agent'        => 'your-agent/1.0',
     *     'protocol_version'  => 1.1,
     *     'follow_location'   => 1,
     *     'max_redirects'     => 10,
     *   ],
     *   ssl => [
     *     'verify_peer'       => true,
     *     'verify_peer_name'  => true,
     *     'allow_self_signed' => false,
     *     'cafile'            => '/path/to/cacert.pem',
     *     'local_cert'        => '/path/to/client.pem',
     *     'passphrase'        => 'secret'
     *   ]
     *
     * Notes
     * - By default, this method enables http.ignore_errors = true so responses with non-2xx
     *   codes still return their bodies. You can override it by passing your own http options.
     * - For custom headers, supply a CRLF-separated string in http.header.
     *
     * Returns
     * - string: The file/stream contents.
     *
     * @throws \Exception when $pathname is empty or cannot be read.
     */
    public static function fileReadOnce(string $pathname, bool $refresh = false, array $streamparams = []): string
    {
        if (!$pathname) {
            throw new \Exception('empty pathname');
        }
        $useInclude = !(
          str_starts_with($pathname, 'http://') ||
          str_starts_with($pathname, 'https://')
        );
        $context = stream_context_create(array_merge([
          'http' => ['ignore_errors' => true]
        ], $streamparams));

        if ($refresh || empty(self::$filecache[$pathname])) {
            $data = file_get_contents($pathname, $useInclude, $context);
            if ($data === false) {
                throw new \Exception(Core::toLog('file not found: ' . $pathname, error_get_last()));
            }
            self::$filecache[$pathname] = $data;
        }
        return self::$filecache[$pathname];
    }

    public static function fileWrite(string $pathname, string $data, int $mode = 0, bool $createdir = false): void
    {
        if ($createdir) {
            self::dirCreate($pathname);
        }
        self::$filecache[$pathname] = $data;
        file_put_contents($pathname, $data, $mode);
    }

    public static function dirCreate(string $pathname, bool $isfile = true): string
    {
        $dirname = $pathname;
        if ($isfile) {
            $dirname = dirname($pathname);
        }
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }
        return $dirname;
    }

    public static function jsonRead(string $data): array
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
        } catch (\JsonException $ex) {
            throw new \JsonException(Core::toLog($ex->getMessage(), $data));
        }
    }

    public static function jsonWrite(mixed $data, int $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT): string
    {
        return json_encode($data, $flags);
    }

    public static function hasInterface(string $classname, string $interfacename): bool
    {
        if ($interfaces = class_implements($classname)) {
            foreach ($interfaces as $interface) {
                if ($interface === $interfacename) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function getArgs(string $method, array $func_get_args): array
    {
        $out = [];
        [$instance, $methodname] = explode('::', $method);
        foreach ((new \ReflectionMethod($instance, $methodname))->getParameters() as $key => $param) {
            $name = $param->getName();
            $out[$name] = $func_get_args[$key];
        }
        return $out;
    }

    public static function getUid(int $len = 6): string
    {
        $out = '';
        while (strlen($out) < $len) {
            $out = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes($len))), 0, $len);
        }
        return $out;
    }

    public static function cleanFilename(string $filename, bool $keepdir = false): string
    {
        $parts = explode('.', $filename);
        $ext = '';
        if (count($parts) > 1) {
            $ext = array_pop($parts);
        }
        $filename = implode('', $parts);
        if ($ext) {
            $filename = implode('.', [$filename, $ext]);
        }
        if ($keepdir) {
            $regex = '/[^\w\.\-äüöÖÄÜß@\/]/';
        } else {
            $regex = '/[^\w\.\-äüöÖÄÜß@]/';
        }
        return preg_replace($regex, '_', $filename);
    }

    /**
     * @param iterable $data
     * @param callable $fnk
     * @return array
     * i know this is strange but
     * using Core::iterate($data,function(SplFileInfo $value,int $key){ does make foreach typesave});
     */
    public static function iterate(iterable $data, callable $fnk, bool $keyvalue = false, bool $merge = false): iterable
    {
        $out = [];
        foreach ($data as $key => $value) {
            $res = $fnk($value, $key);
            if ($res === null) {
                continue;
            }
            // yields are collected not every fnk call gives back random yield returns
            if ($res instanceof \Generator) {
                $loop = $res;
            } else {
                $loop = [$res];
            }

            foreach ($loop as $tmp) {
                if ($tmp) {
                    if ($keyvalue && is_iterable($tmp)) {
                        [$k, $v] = $tmp;
//                        Core::echo(__METHOD__, 'tmp', $k, $v);
                        if ($merge) {
                            $last = Core::getValue($k, $out, []);
                            if (is_array($last)) {
                                $out[$k] = array_merge($last, $v);
                            } else {
                                Core::echo(__METHOD__, 'Cant array merge string', $last);
                            }
                        } else {
                            $out[$k] = $v;
                        }
                    } else {
                        if ($merge) {
                            if (is_array($tmp)) {
                                $out = array_merge($out, $tmp);
                            } else {
                                Core::echo(__METHOD__, 'Wrong  output:', $tmp);
                            }
                        } else {
                            $out[] = $tmp;
                        }
                    }
                }
            }
        }
        return $out;
    }

    public static function log(mixed ...$arguments): array
    {
        if (count($arguments)) {
            $method = $arguments[0];
            if (!in_array($method, Config::$noecho)) {
                self::$log[] = self::toLog(...$arguments);
            }
        }
        return self::$log;
    }

    public static function getNamespace(string $file): string
    {
        $part = explode('namespace', self::fileReadOnce($file), 2)[1] ?? '';
        return trim(explode(';', $part, 2)[0]) ?: '';
    }

    public static function normalizePath(string $path): string
    {
        $path = strtr($path, '\\', '/');
        return rtrim($path, '/') . '/';
    }

    public static function value(int|string $key, array $data, mixed $default = '', bool $defaultIfemtpy = false): mixed
    {
        return self::getValue($key, $data, $default, $defaultIfemtpy);
    }

    /**
     * @param array|null $data
     * @return mixed
     */
    public static function shift(array|null $data): mixed
    {
        if (is_array($data)) {
            if (is_numeric(array_key_first($data))) {
                return array_shift($data);
            }
        }
        return $data;
    }

    public static function loop(array|int $data, callable $fnk, ?int $extract = null): array
    {
        $out = [];
        $ind = 1;
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $val = $fnk($value, $key, $out);
                if ($val !== null) {
                    if ($extract !== null) {
                        if (is_array($val)) {
                            $out[$ind++] = $val[$extract];
                        } else {
                            $out[$ind++] = $val;
                        }
                    } else {
                        $out[$ind++] = $val;
                    }
                }
            }
        } else {
            for ($a = 0; $a < $data; $a++) {
                $val = $fnk('', $a);
                if ($val !== null) {
                    if ($extract !== null) {
                        if (is_array($val)) {
                            $out[$ind++] = $val[$extract];
                        } else {
                            $out[$ind++] = $val;
                        }
                    } else {
                        $out[$ind++] = $val;
                    }
                }
            }
        }
        return $out;
    }

    public static function fileInclude(string $pathname, mixed $default = ''): mixed
    {
        if (file_exists($pathname)) {
            return include($pathname);
        } else {
            return $default;
        }
    }

    public static function catch(callable $fnk, bool $echo = true): mixed
    {
        try {
            return $fnk();
        } catch (\Throwable $ex) {
            if ($echo) {
                $trace = self::value(1, $ex->getTrace(), []);
                Core::echo(__METHOD__, self::value('file', $trace), self::value('line', $trace), $ex);
            }
            return null;
        }
    }

    public static function runWith(string $key, array $data, callable $fn, mixed $default = ''): mixed
    {
        $data = self::getValue($key, $data);
        if (!empty($data)) {
            return $fn($data);
        } else {
            return $default;
        }
    }

    public static function toArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            switch (true) {
//                case is_a($value, Context::class):
//                    $out[$key] = $value->response()->getData();
//                    Core::echo(__METHOD__, 'hier');
//                    break;
//                case is_a($value, Response::class):
//                    $out[$key] = (string)$value;
//                    Core::echo(__METHOD__, 'hier');
//                    break;
                case is_object($value):
                    $reflection = new \ReflectionClass($value);
                    $properties = $reflection->getProperties();
                    $objdata = [];
                    foreach ($properties as $property) {
                        // Allow access to private/protected properties
                        $property->setAccessible(true);
                        if ($property->isInitialized($value)) {
                            $entry = $property->getValue($value);
                            if (!is_scalar($entry)) {
                                $entry = self::toArray([$entry]);
                            }
                            $objdata[$property->getName()] = $entry;
                        }
                    }
                    $out[$key] = $objdata;
                    break;
                case is_array($value):
                    $out[$key] = self::toArray($value);
                    break;
                default:
                    $out[$key] = $value;
            }
        }
        return $out;
    }

}
