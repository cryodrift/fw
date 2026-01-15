<?php
//declare(strict_types=1);

namespace cryodrift\fw;

class Path
{


    public function __construct(protected array $parts = [], protected array $named = [])
    {
        if (empty($parts)) {
            $this->parts = array_values($named);
        }
    }

    public function setParts(array $parts): void
    {
        $this->parts = $parts;
    }

    public function getPart(int $pos = 0, string $default = ''): string
    {
        return Core::getPart($this->parts, $pos, $default);
    }

    public function nameParts(...$arguments)
    {
        $length = min(count($arguments), count($this->parts));
        $keys = array_slice($arguments, 0, $length);
        $values = array_slice($this->parts, 0, $length);
        $this->named = array_combine($keys, $values);
        return $this;
    }

    public function getByName(string $name, string|null $default = null): string|null
    {
        if (array_key_exists($name, $this->named)) {
            return $this->named[$name];
        } else {
            return $default;
        }
    }

    public function getNamed(): array
    {
        return $this->named;
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function getString(string $delim = '/', int $offset = 0, int|null $lenght = null): string
    {
        $found = array_slice($this->parts, $offset, $lenght);
        return implode($delim, $found);
    }

    public function modifyNamed(array $map): Path
    {
        return new Path([], array_merge($this->named, $map));
    }

    public static function fromUrl(string $url): Path
    {
        $urlparts = parse_url($url);
        return new self(explode('/', trim($urlparts['path'], '/')));
    }

}
