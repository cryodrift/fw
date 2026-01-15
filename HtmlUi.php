<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\interface\View;

class HtmlUi implements View
{

    protected array $attributes = [];
    protected static array $uistate = [];
    protected array $lazytemplate = ['pathname' => false, 'block' => '', 'fromblock' => false, 'outerblock' => false];

    protected static array $cache = [];

    public static function setUistate(array $uistate): void
    {
        self::$uistate = $uistate;
    }

    public function __construct(protected string $template = '')
    {
    }

    public function __toString(): string
    {
        return $this->render();
    }


    protected function render(): string
    {
        if ($this->isLazy()) {
            $content = self::loadFile($this->lazytemplate['pathname']);
            $block = $this->lazytemplate['block'];
            if ($block) {
                $content = self::getBlock($block, $content);
            }
            $this->template = $content;
            if ($this->lazytemplate['fromblock']) {
                $this->template = self::extractBlock($this->template, $this->lazytemplate['fromblock'], $this->lazytemplate['outerblock']);
            }
        }
        $this->renderFiles();
        $this->renderBox();
        return $this->replaceVars($this->template, $this->attributes);
    }

    public function renderBlock(string $name, array $data = []): self
    {
        $parts = explode('{{@}}' . $name . '{{@}}', $this->template);
        $block = '';
        if (count($parts) == 3) {
            foreach ($data as $key => $value) {
                $template = $parts[1];
                if (is_array($value)) {
                    $keys = array_keys($value);
                } else {
                    $keys = [];
                }
                $vars = array_reduce($keys, fn($c, $d) => $c . $d . ': {{' . $d . '}}' . PHP_EOL);
                if ($vars) {
                    $template = str_replace('{{__data__}}', $vars, $template);
                }
                if (is_array($value)) {
                    $block .= $this->replaceVars($template, $value);
                } elseif (is_numeric($key)) {
                    // this renders all values in the empty block
                    $block .= $value;
                }
            }
            $block = $this->renderIf($block, $data);
            $this->template = $parts[0] . $block . $parts[2];
        }
        return $this;
    }


    protected function replaceVars(string $template, array $data): string
    {
        $out = '';
        // render blocks first
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $template = (string)self::fromString($template)->renderBlock((string)$key, $value);
            }
        }
        $template = $this->renderIf($template, $data);
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                if (is_numeric($key)) {
                    $out .= str_replace('{{@data@}}', (string)$value, $template);
                } else {
                    $template = str_replace('{{' . $key . '}}', (string)$value, $template);
                }
            }
        }
        if ($out) {
            return $out;
        }
        if (empty($data)) {
            $template = str_replace('{{@data@}}', '', $template);
        }
        return $template;
    }

    /**
     * {{@file@}}path/filename.html{{@file@}}
     * {{@file@}}path/filename.html|blockname{{@file@}}
     */
    public function renderFiles(): void
    {
        $parts = explode('{{@file@}}', $this->template);
        $out = '';
        if (count($parts) > 1) {
            foreach (array_chunk($parts, 2) as $part) {
                $first = Core::getValue(0, $part);
                $content = Core::getValue(1, $part);
                $out .= $first;
                if ($content) {
                    $fparts = explode('|', $content);
                    $filename = $fparts[0];
                    $block = self::loadFile($filename);
                    if ($block) {
                        $subparser = new self($block);
                        $subparser->renderFiles();
                        $block = $subparser->template;
                        if (count($fparts) > 1) {
                            $block = self::getBlock($fparts[1], $block);
                        }
                        $out .= $block;
                    }
                }
            }
        }
        if ($out) {
            $this->template = $out;
        }
    }

    /**
     * {{@if@}}varname|
     * content
     * {{@if@}}
     */
    public function renderIf(string $template, array $attributes): string
    {
        $parts = explode('{{@if@}}', $template);
        $out = '';
        if (count($parts) > 1) {
            foreach (array_chunk($parts, 2) as $part) {
                $first = Core::getValue(0, $part);
                $content = Core::getValue(1, $part);
                $out .= $first;
                if ($content) {
                    $fparts = explode('|', $content, 2);
                    $varname = $fparts[0];
                    if (Core::value($varname, $attributes)) {
                        $out .= $fparts[1];
                    }
                }
            }
        }
        if ($out) {
            $template = $out;
        }
        return $template;
    }

    protected function renderBox(): void
    {
        $parts = explode('{{@box@}}', $this->template);
        $out = '';
        if (count($parts) > 1) {
            foreach (array_chunk($parts, 4) as $part) {
                $content = Core::getValue(0, $part);
                $opening = Core::getValue(1, $part);
                $childs = Core::getValue(2, $part);
                $closing = Core::getValue(3, $part);
                $out .= $content;
                if ($opening !== $closing) {
                    throw new \Exception('Cant parse recursive boxes');
                }
                if ($opening) {
                    $fparts = explode('|', $opening);
                    $filename = $fparts[0];
                    $block = self::loadFile($filename);
                    if ($block) {
                        $block = str_replace('{{@slot@}}', $childs, $block);
                        $subparser = new self($block);
                        $subparser->renderFiles();
                        $block = $subparser->template;
                        if (count($fparts) > 1) {
                            $block = self::getBlock($fparts[1], $block);
                        }
                        $out .= $block;
                    }
                }
            }
        }
        if ($out) {
            $this->template = $out;
        }
    }

    public function setAttributes(array $data, bool $override = false, bool $secure = true, array $nonsecureitems = [])
    {
        if ($secure) {
            $data = Core::cleanData($data, $nonsecureitems);
        }
        if ($override) {
            $this->attributes = $data;
        } else {
            $this->attributes = array_merge($this->attributes, $data);
        }
        return $this;
    }

    public static function fromFile(string $htmlpath, string $block = ''): HtmlUi
    {
        $htmlpath = Main::path($htmlpath);
        $ui = new self();
        $ui->lazyFile($htmlpath, $block);
        return $ui;
    }


    public function lazyFile(string $path, string $block = ''): void
    {
        $this->lazytemplate['pathname'] = $path;
        $this->lazytemplate['block'] = $block;
    }

    public function lazyBlock(string $name, bool $outerblock = false): void
    {
        $this->lazytemplate['fromblock'] = $name;
        $this->lazytemplate['outerblock'] = $outerblock;
    }

    public function isLazy(): bool
    {
        //TODO this is wrong we should test for value but it cant be bool
        return $this->lazytemplate['pathname'] !== false;
    }

    public function fromBlock(string $name, bool $outerblock = false): self
    {
        if ($this->isLazy()) {
            $out = clone $this;
            $out->lazyBlock($name, $outerblock);
            $out->setAttributes($this->attributes);
            return $out;
        } else {
            return self::fromString(self::extractBlock($this->template, $name, $outerblock))->setAttributes($this->attributes);
        }
    }

    public static function fromString(string $html = '{{@data@}}', string $block = ''): HtmlUi
    {
        if ($block) {
            $delim = '{{@}}' . $block . '{{@}}';
            $html = $delim . $html . $delim;
        }
        $ui = new self($html);
        return $ui;
    }

    protected static function extractBlock(string $content, string $name, bool $outerblock = false)
    {
        $out = '';
        $delim = '{{@}}' . $name . '{{@}}';
        $parts = explode($delim, $content);
        if (count($parts) == 3) {
            $out = $parts[1];
            if ($outerblock) {
                $out = $delim . $out . $delim;
            }
        }
        return $out;
    }

    public static function makeActive(array $data, string|int $value, string $key = 'id')
    {
        return array_map(function ($a) use ($value, $key) {
            $a['active'] = $a[$key] == $value ? 'active g-active' : '';
            return $a;
        }, $data);
    }

    public static function makeSelected(array $data, array $selected, string $name = 'id', string $value = 'active g-active')
    {
        return Core::addData($data, function ($a) use ($selected, $name, $value) {
            $a['active'] = in_array(Core::getValue($name, $a), $selected) ? $value : '';
            return $a;
        });
    }


    public static function addQuery(Context $ctx, array $data, array $map, array $queryparts, bool $withuistate = true)
    {
        $querydata = self::getQueryData($ctx, $queryparts, $withuistate);

//        Core::echo(__METHOD__, 'querydata', $querydata);
        $data = Core:: addData($data, function ($a) use ($querydata, $map, $data) {
            // override querydata from url with data values
            foreach ($map as $k => $v) {
                if ($k == 'query') {
                    throw new \Exception('map key query not allowed');
                }
                if (array_key_exists($k, $a)) {
                    if (is_array($a[$k])) {
                        if (empty($a[$k])) {
                            unset($querydata[$v]);
                        } else {
                            $querydata[$v] = $a[$k];
                        }
                    } else {
                        $querydata[$v] = trim((string)$a[$k]);
                    }
                }
            }

            foreach ($querydata as $k => $v) {
                if (empty($v)) {
                    unset($querydata[$k]);
                }
            }

            if (count($querydata)) {
                $a['query'] = '?' . trim(http_build_query($querydata));
            }

            if (!array_key_exists('query', $a)) {
                $a['query'] = '';
            }
            return $a;
        });
//        Core::echo(__METHOD__, $data);
        return $data;
    }

    public static function getQuery(Context $ctx, array $parts = [], bool $withuistate = true)
    {
        return http_build_query(self::getQueryData($ctx, $parts, $withuistate));
    }

    public static function getQueryData(Context $ctx, array $queryparts = [], bool $withuistate = true)
    {
        if ($withuistate) {
            $queryparts = array_merge($queryparts, self::$uistate);
        }
        return Core::extractData($ctx->request()->vars(), $queryparts, false);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public static function getBlock(string $name, string $content)
    {
        return '{{@}}' . $name . '{{@}}' . $content . '{{@}}' . $name . '{{@}}';
    }

    public static function cache(bool $refresh = false)
    {
        $pathname = Main::$rootdir . Config::$datadir . 'templates.cache.ser';
        if ($refresh && file_exists($pathname)) {
            unlink($pathname);
        }
        if (empty(self::$cache)) {
            if (file_exists($pathname)) {
                self::$cache = unserialize(file_get_contents($pathname));
            }
        } else {
            if (!file_exists($pathname)) {
                Core::fileWrite($pathname, serialize(self::$cache));
            }
        }
    }

    protected static function loadFile(string $pathname)
    {
        if (!array_key_exists($pathname, self::$cache)) {
            $pathname2 = Main::path($pathname);
            if (file_exists($pathname2)) {
                self::$cache[$pathname] = file_get_contents($pathname2);
            } else {
                throw new \Exception(Core::toLog(__METHOD__, 'missing file:', $pathname, $pathname2));
            }
        }
        return self::$cache[$pathname];
    }
}
