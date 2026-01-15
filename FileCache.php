<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\interface\Cache;
use cryodrift\fw\interface\Cachegroup;

abstract class FileCache implements Cache, Cachegroup
{

    public function __construct(protected string $cachedir)
    {
    }

    public function set(string $key, string $value): void
    {
        $pathname = $this->cachedir . $key;
        if ($this->cachedir && !file_exists($pathname)) {
            Core::dirCreate($pathname);
        }
        if ($value) {
            Core::fileWrite($pathname, $value);
        }
    }

    public function get(string $key, $default = ''): string
    {
        if ($this->has($key)) {
            return file_get_contents($this->cachedir . $key);
        }
        return $default;
    }

    public function delete(string $key): bool
    {
        $pathname = $this->cachedir . $key;
        return unlink($pathname);
    }

    public function has(string $key): bool
    {
        return file_exists($this->cachedir . $key) && filesize($this->cachedir . $key);
    }

    public function clear(): bool
    {
        $out = true;
        try {
            foreach (Core::dirList($this->cachedir) as $file) {
                if (false === unlink($file->getPathname())) {
                    $out = false;
                }
            }
        } catch (\UnexpectedValueException $ex) {
            $out = false;
        }
        return $out;
    }

    public function setGroup(string $name, string $key, string $value): void
    {
        $data = unserialize($this->get($name));
        if (!is_array($data)) {
            $data = [];
        }
        $this->set($name, serialize(array_merge($data, [$key => $value])));
    }

    public function getGroup(string $name, string $key, $default = ''): string
    {
        $data = unserialize($this->get($name));
        return Core::getValue($key, $data, $default);
    }

    public function deleteGroup(string $name, string $key): bool
    {
        $data = unserialize($this->get($name));
        if ($data && array_key_exists($key, $data)) {
            unset($data[$key]);
            $this->set($name, serialize($data));
            return true;
        }
        return false;
    }

    public function hasGroup(string $name, string $key): bool
    {
        $data = unserialize($this->get($name));
        return $data && array_key_exists($key, $data);
    }

    public function clearGroup(string $name): bool
    {
        return $this->delete($name);
    }
}
