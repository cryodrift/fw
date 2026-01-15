# Core.php - Method Reference

This document provides a comprehensive list of all methods available in the `Core.php` class, which serves as the backbone utility class for the application framework.

## Using as a Composer module

Install the sys module via Composer in your project:

```bash
composer require chrisg/htmlload-sys
```

The package exposes classes under the `sys` namespace via PSR-4 autoloading. Typical imports look like this:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use cryodrift\fw\Config;
use cryodrift\fw\Core;
use cryodrift\fw\Main;
use cryodrift\fw\cli\CliUi; // optional, for CLI helpers

// Load configuration (you can also build your own Config array)
$config = Main::readConfig('cli'); // or 'web'

// Run the framework core (returns string|Response|false depending on mode)
$out = Main::run($config, echo: false);

// Or work directly with Core
$core = new Core($config);
$response = $core->run();

// CLI helper example
CliUi::echoLine('Hello from sys module');
```

## Method List

### Constructor and Context Management

| Method | Description | Params |
|--------|-------------|--------|
| `__construct()` | Initializes the Core class with a configuration and sets up error handling. | Config $config |
| `newContext()` | Creates a new Context object with the given configuration. | Config $config |
| `getContext()` | Returns the current Context object. | - |
| `run()` | Runs the application by processing handlers defined in the configuration. | - |

### System Information

| Method | Description | Params |
|--------|-------------|--------|
| `isUnix()` | Determines if the current operating system is Unix-based. | - |
| `time()` | Measures execution time. If $sum is true, returns total time since request start. | bool $sum = false |
| `env()` | Gets an environment variable value with fallback. | string $name, string $default = '' |

### Object Creation and Reflection

| Method | Description | Params |
|--------|-------------|--------|
| `newObject()` | Creates a new object with automatic dependency injection. | string $classname, Context $ctx, array $config = [] |
| `getParams()` | Gets parameters for a method from a source array. | object\|string $instance, string $methodname, array $source, Context\|null $ctx = null |
| `getParamValue()` | Resolves a parameter value based on its type and context. | Paraminfo $paraminfo, array $source, Context $ctx |
| `methodParams()` | Yields parameter information for a method. | object\|string $instance, string $methodname |
| `getMethods()` | Gets all methods of a class or object. | object\|string $objectOrClass, int\|null $type = null |
| `getDocCommentVars()` | Extracts variables from a doc comment. | string $comment |
| `hasInterface()` | Checks if a class implements a specific interface. | string $classname, string $interfacename |
| `getArgs()` | Maps function arguments to parameter names. | string $method, array $func_get_args |

### Configuration Management

| Method | Description | Params |
|--------|-------------|--------|
| `getConfig()` | Gets configuration for a class. | string $name, Context $ctx, bool $optional = false |

### Array and Data Manipulation

| Method | Description | Params |
|--------|-------------|--------|
| `getValue()` | Safely retrieves a value from an array with a default fallback. | string\|int\|float\|bool $key, mixed $data, mixed $default = '', bool $defaultIfempty = false |
| `getPart()` | Gets a specific part from an array at a given position. | array $parts, int $pos, mixed $default = null, int $length = 1 |
| `extractData()` | Recursively extracts specific keys from a nested array structure. | array $data, array $keys, bool $recursiv = true |
| `removeKeys()` | Removes specific keys from an array. | array $keynames, array $data |
| `extractKeys()` | Extracts specific keys from a nested array structure. | array $data, array $keynames, bool $collect = false |
| `addData()` | Applies a function to each element in a nested array. | array $data, callable $fnk |
| `cleanData()` | Cleans data for output (HTML escaping). | array\|string $data, array $excludes = [] |
| `pop()` | Pops the last element from an array if it's numerically indexed. | array\|null $data |
| `iterate()` | Iterates over data with type-safe callbacks. | iterable $data, callable $fnk |

### File and Directory Operations

| Method | Description | Params |
|--------|-------------|--------|
| `dirList()` | Lists files in a directory with filtering. | string\|iterable $pathoriterator, callable\|null $filter = null, int $skip = FilesystemIterator::SKIP_DOTS |
| `fileReadOnce()` | Reads file content (cached). | string $pathname, bool $refresh = false, array $streamparams = [] |
| `fileWrite()` | Writes to a file. | string $pathname, string $data, int $mode = 0, bool $createdir = false |
| `dirCreate()` | Creates a directory. | string $pathname, bool $isfile = true |
| `cleanFilename()` | Cleans a filename for safe use. | string $filename, bool $keepdir = false |

### Logging and Debugging

| Method | Description | Params |
|--------|-------------|--------|
| `toLog()` | Formats variables for logging. | mixed ...$arguments |
| `echo()` | Logs messages and objects. | mixed ...$args |
| `echoTmp()` | Logs temporary messages. | mixed ...$args |
| `log()` | Adds to the log array and returns all logs. | mixed ...$arguments |

### Type Handling and Conversion

| Method | Description | Params |
|--------|-------------|--------|
| `castValue()` | Casts values to specific types. | mixed $variable, mixed $targetType |
| `jsonRead()` | Converts JSON to array. | string $data |
| `jsonWrite()` | Converts array to JSON. | mixed $data, int $flags = JSON_THROW_ON_ERROR \| JSON_PRETTY_PRINT |
| `getUid()` | Generates a unique ID. | int $length = 6 |

## Detailed Method Descriptions

### `newObject()`

Creates a new object with automatic dependency injection.

**Parameters:**
- `$classname`: The class name to instantiate
- `$ctx`: The context object
- `$config`: Optional configuration array

**Returns:** A new instance of the specified class with dependencies injected

**Example:**
```php
$repository = Core::newObject(Repository::class, $ctx);
```

### `getValue()`

Safely retrieves a value from an array with a default fallback.

**Parameters:**
- `$key`: The key to look for
- `$data`: The array or object to search in
- `$default`: The default value to return if key not found
- `$defaultIfempty`: Whether to return default if value is empty

**Returns:** The value if found, otherwise the default

**Example:**
```php
$id = Core::getValue('id', $data, 0);
```

### `dirList()`

Lists files in a directory with optional filtering.

**Parameters:**
- `$pathoriterator`: The path to list or an existing iterator
- `$filter`: Optional callback function to filter results
- `$skip`: Iterator flags

**Returns:** A RecursiveIteratorIterator for the directory

**Example:**
```php
$files = Core::dirList($path, function($file) {
    return $file->isFile() && fnmatch('*.jpg', $file->getFilename());
});
```

### `cleanData()`

Cleans data for output by applying HTML escaping.

**Parameters:**
- `$data`: The data to clean
- `$excludes`: Array of keys to exclude from cleaning

**Returns:** The cleaned data

**Example:**
```php
$safeData = Core::cleanData($userData, ['password']);
```

### `jsonWrite()`

Converts data to JSON with error handling.

**Parameters:**
- `$data`: The data to convert
- `$flags`: JSON encoding flags

**Returns:** JSON string representation of the data

**Example:**
```php
$jsonString = Core::jsonWrite($array);
```

### `jsonRead()`

Converts JSON to an array with error handling.

**Parameters:**
- `$data`: The JSON string to convert

**Returns:** Array representation of the JSON data

**Example:**
```php
$array = Core::jsonRead($jsonString);
```


### `__construct()`

Initializes Core with a Config, sets up error handling, and creates a Context.

Parameters:
- `$config` (Config): Application configuration

Returns: Core instance

Example:
```php
$core = new Core($config);
```

### `newContext()`

Builds a new Context from Config, populating Request and Response.

Parameters:
- `$config` (Config)

Returns: Context

Example:
```php
$ctx = Core::newContext($config);
```

### `getContext()`

Returns the Core's current Context.

Parameters: none

Returns: Context

### `run()`

Executes configured Handler pipeline. Instantiates each handler with DI and calls `handle($ctx)` until a final Response is produced.

Parameters: none

Returns: Response

Example:
```php
$response = $core->run();
if ($response->isFinal()) { /* ... */ }
```

### `isUnix()`

Detects whether the OS is Unix-like.

Returns: bool

### `time()`

Measures execution time between calls or from request start.

Parameters:
- `$sum` (bool, default false): If true, total time since request start; otherwise delta since last call

Returns: string like `"0.012345 seconds"`

Example:
```php
Core::time(); // warm up
// ... do work
$delta = Core::time();
$total = Core::time(true);
```

### `env()`

Reads a value from .env file (if defined in Config::$envfile), then from $_ENV, falling back to default.

Parameters:
- `$name` (string)
- `$default` (string, default '')

Returns: string

Example:
```php
$db = Core::env('DB_NAME', 'test');
```

### `getParams()`

Resolves constructor or method parameters from a config/source array using reflection and Param attributes.

Parameters:
- `$instance` (object|string): Object or class name
- `$methodname` (string)
- `$source` (array): Values to hydrate from
- `$ctx` (Context|null): Context for DI

Returns: array of parameter name => value

### `getParamValue()`

Internal helper returning a name=>value array for one parameter based on Param info, source data, defaults, and DI rules.

Parameters:
- `$paraminfo` (Paraminfo)
- `$source` (array)
- `$ctx` (Context)

Returns: array

### `methodParams()`

Yields reflection-backed Paraminfo entries for a method.

Parameters:
- `$instance` (object|string)
- `$methodname` (string)

Returns: iterable of Paraminfo

### `getMethods()`

Returns method names of a class/object. Optional `$type` to filter by ReflectionMethod constants (e.g., IS_PUBLIC).

Parameters:
- `$objectOrClass` (object|string)
- `$type` (int|null)

Returns: array of strings

### `getDocCommentVars()`

Parses a docblock and returns `@var name=value` style variables as an associative array.

Parameters:
- `$comment` (string)

Returns: array

### `hasInterface()`

Checks if a class implements a specific interface.

Parameters:
- `$classname` (string)
- `$interfacename` (string)

Returns: bool

### `getArgs()`

Maps variadic `func_get_args()` to named parameters using reflection of a callable string like `Class::method`.

Parameters:
- `$method` (string)
- `$func_get_args` (array)

Returns: array name=>value

### `getConfig()`

Loads a named configuration section for a class or feature from Context.

Parameters:
- `$name` (string)
- `$ctx` (Context)
- `$optional` (bool): If true, return empty array when not found; otherwise may throw

Returns: array

### `getPart()`

Returns a slice of an array by position and length, with default if out of range.

Parameters:
- `$parts` (array)
- `$pos` (int)
- `$default` (mixed, default null)
- `$length` (int, default 1)

Returns: mixed (single value if length=1, otherwise array)

### `extractData()`

Recursively extracts only the specified keys from nested arrays/objects, preserving structure.

Parameters:
- `$data` (array)
- `$keys` (array)
- `$recursiv` (bool, default true)

Returns: array

### `removeKeys()`

Removes a set of keys from an array (non-recursive).

Parameters:
- `$keynames` (array of strings)
- `$data` (array)

Returns: array cleaned copy

### `extractKeys()`

Collects a subset of keys from nested arrays. When `$collect` is true, aggregates found values; otherwise mirrors structure.

Parameters:
- `$data` (array)
- `$keynames` (array)
- `$collect` (bool, default false)

Returns: array

### `addData()`

Applies a callable to each element (recursively for arrays), returning the transformed data.

Parameters:
- `$data` (array)
- `$fnk` (callable)

Returns: mixed

### `pop()`

If `$data` is a numerically-indexed array, returns `array_pop($data)`. Otherwise returns the input unchanged.

Parameters:
- `$data` (array|null)

Returns: mixed

### `iterate()`

Iterates over any iterable and applies a type-safe callback `fn($value, $key): mixed`. Returns an array of results.

Parameters:
- `$data` (iterable)
- `$fnk` (callable)

Returns: array

Example:
```php
$out = Core::iterate([1,2,3], fn($v)=>$v*2); // [2,4,6]
```

### `fileReadOnce()`

Reads a file into memory with in-process caching. Set `$refresh` to bypass cache. Allows stream context params.

Parameters:
- `$pathname` (string)
- `$refresh` (bool, default false)
- `$streamparams` (array, default [])

Returns: string (file contents)

### `fileWrite()`

Writes data to a file. Optionally creates the directory and sets chmod mode.

Parameters:
- `$pathname` (string)
- `$data` (string)
- `$mode` (int, default 0): If >0, apply chmod
- `$createdir` (bool, default false)

Returns: void

### `dirCreate()`

Ensures a directory exists. When `$isfile` is true, treats `$pathname` as a file path and creates its parent directory.

Parameters:
- `$pathname` (string)
- `$isfile` (bool, default true)

Returns: string absolute/normalized path

### `cleanFilename()`

Sanitizes a filename; optionally preserves directory segments.

Parameters:
- `$filename` (string)
- `$keepdir` (bool, default false)

Returns: string

### `toLog()`

Formats arguments as a readable log line. Scalars are concatenated; arrays/objects are printed; Exceptions expanded.

Parameters:
- `...$arguments` (mixed)

Returns: string (with trailing newline)

### `echo()`

Logs a named message via CliUi unless the first argument (method tag) is in `Config::$noecho`.

Parameters:
- `...$args` (mixed): First element is a tag/method name

Returns: void

### `echoTmp()`

Writes a temporary/progress message via CliUi.

Parameters:
- `...$args` (mixed)

Returns: void

### `log()`

Appends a formatted message to Core's static log buffer and returns the buffer.

Parameters:
- `...$arguments` (mixed)

Returns: array of log entries

### `castValue()`

Casts a value to the type of `$targetType` (by inspecting its type). Supports boolean, integer, double, string, array, object, NULL; throws on unsupported types.

Parameters:
- `$variable` (mixed)
- `$targetType` (mixed): Value whose type is used as the target type

Returns: mixed (cast value)

### `jsonRead()`

Decodes JSON to array with exceptions on errors.

Parameters:
- `$data` (string)

Returns: array

### `jsonWrite()`

Encodes data to JSON with JSON_THROW_ON_ERROR and pretty-print by default.

Parameters:
- `$data` (mixed)
- `$flags` (int)

Returns: string

### `getUid()`

Generates a short random alphanumeric ID.

Parameters:
- `$length` (int, default 6)

Returns: string
