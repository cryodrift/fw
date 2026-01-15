<?php

//declare(strict_types=1);

namespace cryodrift\fw;

class Request
{

    protected Path $path;
    protected array $vars = [];
    protected string $route = '';
    // every argument that has - on first position is a param
    protected array $params = [];
    protected array $defaultvars = [];
    // all arguments except params
    protected array $args = [];

    public function __construct()
    {
        global $_REQUEST;
        if (Config::isCli()) {
            $this->restore();
        } else {
            $url = parse_url(Core::getValue('REQUEST_URI', $_SERVER));
            $this->path = new Path(explode('/', trim($url['path'], '/')));
            $this->vars = $_REQUEST;
        }
    }

    public function setPath(Path $path): void
    {
        $this->path = $path;
    }

    public function vars(string|null $name = null, mixed $default = '', bool $defaultIfEmpty = false): mixed
    {
        if (is_null($name)) {
            return $this->vars;
        }
        return Core::getValue($name, $this->vars, $default, $defaultIfEmpty);
    }

    public function setVar(string $name, string|int $content): void
    {
        $this->vars[$name] = $content;
        $this->castVars();
    }

    public function remVar(string $name): void
    {
        unset($this->vars[$name]);
    }

    public function hasVars(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->vars($name)) {
                return true;
            }
        }
        return false;
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function files(): array
    {
        return $_FILES;
    }

    public function route(string $route = ''): string
    {
        if ($route) {
            $this->route = $route;
        }
        return $this->route;
    }

    public function __clone(): void
    {
        $this->path = clone $this->path;
    }

    public function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    public function setDefaultVars(array $getvar_defaults): void
    {
        $this->defaultvars = $getvar_defaults;
        $this->castVars();
    }

    protected function castVars(): void
    {
        foreach ($this->defaultvars as $key => $value) {
            $this->vars[$key] = Core::castValue(Core::getValue($key, $this->vars, $value), $value);
        }
    }

    public function param(string $name, string|null $default = '', bool $defaultIfempty = false): string|null
    {
        return Core::getValue($name, $this->getParams(), $default, $defaultIfempty);
    }

    public function hasParam(string $name): bool
    {
        return array_key_exists($name, $this->getParams());
    }

    public function getParams(): array
    {
        if (empty($this->params)) {
            $this->restore();
        }
        return $this->params;
    }

    /**
     * @param int $pos
     * @param string $default
     * @return string
     * get console argumet by its position
     */
    public function arg(int $pos, string $default = ''): string
    {
        return Core::getPart($this->args, $pos, $default);
    }

    /**
     * @param string $name
     * @return $this
     * add argument to stack
     */
    public function addArg(string $name): self
    {
        $this->args[] = $name;
        return $this;
    }

    /**
     *
     */
    public function getArgAfter(string $argname, string $startat = ''): string
    {
        $out = '';
        foreach ($_SERVER['argv'] as $key => $value) {
            if ($startat && $startat !== $value) {
                continue;
            }
            $startat = '';
//            Core::echo(__METHOD__, $key, $argname, $startat, $value);
            if ($value === $argname && array_key_exists($key + 1, $_SERVER['argv'])) {
                return $_SERVER['argv'][$key + 1];
            }
        }
        return $out;
    }

    public function args(): array
    {
        return $this->args;
    }

    public function shiftArgs(): self
    {
        array_shift($this->args);
        return $this;
    }

    /**
     * Extract CLI params from an argv array (or current process if null).
     * Works in static context.
     */
    public static function getCliParams(array|null $argv = null): array
    {
        if (!Config::isCli()) {
            return [];
        }
        $argv = $argv ?? Core::getValue('argv', $_SERVER, []);
        $params = [];
        foreach ($argv as $value) {
            if (strpos($value, '-') === 0) {
                $parts = explode('=', ltrim($value, '-'), 2);
                $name = Core::getValue(0, $parts);
                if (array_key_exists($name, $params)) {
                    if (is_array($params[$name])) {
                        $params[$name][] = Core::getValue(1, $parts);
                    } else {
                        $first = $params[$name];
                        $params[$name] = [];
                        $params[$name][] = $first;
                        $params[$name][] = Core::getValue(1, $parts);
                    }
                } else {
                    $params[$name] = Core::getValue(1, $parts);
                }
            }
        }
        return $params;
    }

    /**
     * @return $this
     * restore all arguments and params to its original state
     */
    public function restore(): self
    {
        if (Config::isCli()) {
            $this->args = $_SERVER['argv'];
            // extract params using static helper
            $this->params = self::getCliParams($this->args);
            // keep only non-param args
            $this->args = array_values(array_filter($this->args, fn($v) => !(strpos($v, '-') === 0)));

            $found = array_filter($this->args, fn($a) => (strpos($a, 'http') === 0 || strpos($a, '/') === 0));

            if (!empty($found)) {
                $url = parse_url(array_shift($found));
                parse_str(Core::getValue('query', $url, ''), $vars);
                $this->vars = $vars;
                $this->path = new Path(explode('/', trim(Core::getValue('path', $url), '/')));
            } else {
                $this->path = new Path([]);
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     * set/add a param with value, this does not validate param format
     */
    public function setParam(string $name, string $value = ''): self
    {
        $this->params[$name] = $value;
        return $this;
    }

    public function getHeaders(string $name = ''): array|string
    {
        $headers = [];
        if (!function_exists('getallheaders')) {
            // If the getallheaders function does not exist (like in some CGI environments)

            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $hname = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[strtolower($hname)] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }
        return Core::getValue(strtolower($name), $headers, $headers);
    }

    public function isPost(): bool
    {
        if (Config::isCli()) {
            return $this->hasParam('post');
        } else {
            return Core::getValue('REQUEST_METHOD', $_SERVER, 'GET') === 'POST';
        }
    }

    public function stdIN(): string|false
    {
        return stream_get_contents(\STDIN);
    }
}
