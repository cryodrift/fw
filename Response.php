<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use Exception;
use Stringable;

class Response implements \Serializable
{

    const string HEADER_NOTMODIFIED = "HTTP/1.1 304 Not Modified";
    const string HEADER_NOTFOUND = "HTTP/1.0 404 Not Found";
    const string HEADER_CONTENT_TYPE = "Content-Type: ";
    const string HEADER_BAD_REQUEST = "HTTP/1.1 400 Bad Request";

    const int STATUS_INVALID = 0;
    const int STATUS_VALID = 1;
    const int STATUS_FINAL = 2;

    protected int $status = 0;
    protected array $headers = [];
    protected array $data = [];
    protected array $afterrunners = [];
    protected string|Stringable|HtmlUi $content;


    public function __construct(string|Stringable $content)
    {
        $this->setContent($content);
    }

    public function addHeader(string $value, bool $once = false): void
    {
        $this->headers[] = $value;
        if ($once) {
            [$name, $val] = explode(': ', $value, 2);
            $loc = array_filter($this->headers, fn($h) => str_starts_with(strtolower($h), $name . ':'));
            if ($loc) {
                $this->headers = array_filter($this->headers, fn($h) => !str_starts_with(strtolower($h), $name . ':'));
                $this->headers[] = array_pop($loc);
            }
        }
        $loc = array_filter($this->headers, fn($h) => str_starts_with(strtolower($h), 'location:'));
        if ($loc) {
            $this->headers = array_filter($this->headers, fn($h) => !str_starts_with(strtolower($h), 'location:'));
            $this->headers[] = array_pop($loc);
        }
    }

    public function getHeaders(bool $namedkeys = false): array
    {
        if ($this->data) {
            if (!Config::isCli()) {
                $this->addHeader(Response::HEADER_CONTENT_TYPE . FileHandler::mimetypes()['json'], true);
            }
        }

        $out = $this->headers;
        if ($namedkeys) {
            foreach ($this->headers as $header) {
                $parts = explode(': ', $header, 3);
                $out[$parts[0]] = $parts[1];
            }
        }
        return $out;
    }

    public function setHeaders(array $headers): self
    {
        foreach ($headers as $header) {
            $this->addHeader($header);
        }
        return $this;
    }

    public function remHeaders(): array
    {
        $out = $this->headers;
        $this->headers = [];
        return $out;
    }

    public function setRedirect(string $url): self
    {
        $this->addHeader('location: ' . $url);
        return $this;
    }

    public function getCookies(): array
    {
        return Core::iterate($this->headers, function ($h) {
            [$name, $value] = explode(': ', $h, 2);
            if ($name === 'Set-Cookie') {
                return self::cleanHeader($value);
            }
        });
    }

    public function addCookie(string $name, string $value = '', int $expires = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false): self
    {
        $out = [];
        $out[] = $name . '=' . $value;
        if ($expires !== 0) {
            $out[] = 'Max-Age=' . $expires;
        }
        $out[] = 'Path=' . $path;
        $out[] = 'Domain=' . $domain;
        if ($secure) {
            $out[] = 'Secure';
        }
        if ($httponly) {
            $out[] = 'HttpOnly';
        }
        $out[] = 'SameSite=Strict';

        $this->addHeader('Set-Cookie: ' . implode('; ', $out));
        return $this;
    }


    public function setContent(string|Stringable $content): self
    {
        $this->status(self::STATUS_VALID);
        $this->content = $content;
        return $this;
    }

    public function getContent(): string|Stringable|HtmlUi
    {
        return $this->content;
    }

    public function status(?int $status = null): int
    {
        // protect final status from override
        if ($status !== null && $this->status === self::STATUS_INVALID) {
            $this->status = $status;
        }
        return $this->status;
    }

    public function isRaw(Context $ctx): bool
    {
        if (Config::isCli() && ($ctx->request()->hasParam('echo') || $ctx->request()->hasParam('debug'))) {
            return false;
        } else {
            return true;
        }
    }

    public function isDebug(Context $ctx): bool
    {
        if (Config::isCli() && $ctx->request()->hasParam('debug')) {
            return true;
        } else {
            return false;
        }
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function setStatusFinal(): self
    {
        $this->status = self::STATUS_FINAL;
        return $this;
    }

    public function setStatusValid(): self
    {
        // protect final status from override
        $this->status(self::STATUS_VALID);
        return $this;
    }

    public function setStatusInvalid(): self
    {
        $this->status = self::STATUS_INVALID;
        return $this;
    }

    public function isHandled(): bool
    {
        return $this->status !== self::STATUS_INVALID;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->setStatusValid();
        $this->data = $data;
    }

    public function addAfterRunner(callable $fnk): self
    {
        $this->afterrunners[] = $fnk;
        return $this;
    }

    public function __serialize(): array
    {
        return [
          'data' => $this->data,
          'headers' => $this->headers,
          'content' => $this->content
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->headers = $data['headers'];
        $this->content = $data['content'];
    }

    public function serialize()
    {
    }

    public function unserialize(string $data)
    {
    }

    public function __toString(): string
    {
        if ($this->data) {
            $out = Core::toArray($this->data);
            if (Config::isCli()) {
                $content = Core::toLog($out);
            } else {
                $content = json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                $this->addHeader(Response::HEADER_CONTENT_TYPE . FileHandler::mimetypes()['json'], true);
            }
        } else {
            $content = (string)$this->content;
        }

        foreach ($this->afterrunners as $runner) {
            call_user_func($runner, $this);
        }
        if (!Config::isCli() && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
            $content = gzencode($content, 9);
            $this->addHeader('Content-Encoding: gzip');
            $this->addHeader('Content-Length: ' . strlen($content));
            foreach ($this->getHeaders() as $k => $v) {
                header(self::cleanHeader($v), false);
            }
        }
        return $content;
    }

    public static function cleanHeader(string $header): string
    {
        return str_replace(["\r", "\n"], '', trim($header));
    }

}
