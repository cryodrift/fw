<?php

namespace cryodrift\fw;

class Reflect
{

    public static function isInitialized(object $obj, string $property): bool
    {
        return new \ReflectionProperty($obj::class, $property)->isInitialized($obj);
    }

    public static function hasMethod(string|object $objclass, string $methodname): bool
    {
        return new \ReflectionClass($objclass::class)->hasMethod($methodname);
    }

    public static function getMethod(object|string $class, string $methodname): \ReflectionMethod
    {
        return new \ReflectionClass($class::class)->getMethod($methodname);
    }

    public static function getMethods(object|string $class): array
    {
        return new \ReflectionClass($class)->getMethods();
    }

    public static function getDocVarValue(\ReflectionMethod $method, string $varname): string
    {
        $pattern = "/^\s*\*\s*@" . $varname . "\s+([^\s]+)\s*$/m";
        $doc = $method->getDocComment();
        if ($doc !== false && preg_match($pattern, $doc, $matches) !== false) {
//            Core::echo(__METHOD__, $matches);
            return Core::value(1, $matches);
        }
        return '';
    }


    /**
     * @param string $comment
     * @return array
     */
    public static function getDocVars(\ReflectionMethod $method): array
    {
        $out = [];
        $doc = $method->getDocComment();
        if ($doc !== false && preg_match_all('/@(\w+)\ *(.*)/', $doc, $matches)) {
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

    public static function getProps(object $obj, int $type = \ReflectionProperty::IS_PUBLIC): array
    {
        return new \ReflectionClass($obj)->getProperties($type);
    }

    /**
     * get params for a method from config
     * @param string $classname
     * @param string $methodname
     * @param Context $ctx
     * @return array
     */
    public static function getMethodParams(string|object $classname, string $methodname, Context $ctx, array $params = []): array
    {
        // Get params for $classname::$methodname from reflection
        try {
            if (is_object($classname)) {
                $classname = get_class($classname);
            }
            $reflectionMethod = new \ReflectionMethod($classname, $methodname);
            $reflectionParams = $reflectionMethod->getParameters();

            // Extract params config
            $data = Core::value($classname, $ctx->config()[Config::KEY_CONFIG]);
            // If string it must be another classname
            while (is_string($data) && $data) {
                $data = Core::value($data, $ctx->config()[Config::KEY_CONFIG]);
            }
            if ($data === '') {
                // No data found in config
                $data = [];
            }
            $data = array_merge($data, $params);

            $result = [];

            foreach ($reflectionParams as $param) {
                $paramName = $param->getName();
                $paramType = $param->getType();

                // Skip if parameter already exists in $params
                if (array_key_exists($paramName, $result)) {
                    continue;
                }

                // If param is type Context make value $ctx
                if ($paramType && !$paramType->isBuiltin() && $paramType->getName() === Context::class) {
                    $result[$paramName] = $ctx;
                    continue;
                }

                // If param is type Config make value $ctx->config()
                if ($paramType && !$paramType->isBuiltin() && $paramType->getName() === Config::class) {
                    $result[$paramName] = $ctx->config();
                    continue;
                }

                if ($paramType && !$paramType->isBuiltin()) {
                    $result[$paramName] = self::newObject($paramType->getName(), $ctx);
                    continue;
                }

                // Find values for this params in $ctx->config() format [$classname]['paramname']=value
                if (is_array($data) && array_key_exists($paramName, $data)) {
                    $paramValue = $data[$paramName];

                    // If value is Classname make instance with Reflect::newObject(...)
                    if (is_string($paramValue) && class_exists($paramValue)) {
                        $result[$paramName] = self::newObject($paramValue, $ctx);
                    } else {
                        $result[$paramName] = $paramValue;
                    }
                } elseif ($param->isDefaultValueAvailable()) {
                    // Use default value if available
                    $result[$paramName] = $param->getDefaultValue();
                }
            }

            return $result;
        } catch (\Throwable $ex) {
            if (!str_contains($ex->getMessage(), '__construct() does not exist')) {
                Core::echo(__METHOD__, get_debug_type($ex), $ex->getMessage());
            }
            return [];
        }
    }

    public static function newObject(string $classname, Context $ctx, array $params = []): mixed
    {
        $filename = new \ReflectionClass($classname)->getFileName();
        $ctx->config()->addConfig(dirname($filename));
        $ctx->config()->addConfig(dirname($filename, 2));
        $object = new $classname(...self::getMethodParams($classname, '__construct', $ctx, $params));
        return $object;
    }

    public static function valueToString(mixed $value): string
    {
        return match ($value) {
            0 => '0',
            true => 'true',
            false => 'false',
            '' => '""',
            default => $value
        };
    }

}
