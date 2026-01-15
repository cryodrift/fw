<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use cryodrift\fw\cli\Colors;

class Context
{

    public array $data = [];
    /**
     *
     */
    const string CONFIG = 'config';
    /**
     *
     */
    const string SERVER = 'server';
    /**
     *
     */
    const string RESPONSE = 'response';
    /**
     *
     */
    const string REQUEST = 'request';

    protected string $language;


    public function __construct(Config $config, array $server, Response $response, Request $request)
    {
        $this->data[self::CONFIG] = $config;
        $this->data[self::SERVER] = $server;
        $this->data[self::RESPONSE] = $response;
        $this->data[self::REQUEST] = $request;
    }

    public function response(Response|null $obj = null): Response
    {
        if ($obj) {
            $this->data[self::RESPONSE] = $obj;
        }
        return $this->data[self::RESPONSE];
    }

    public function request(Request|null $obj = null): Request
    {
        if ($obj) {
            $this->data[self::REQUEST] = $obj;
        }
        return $this->data[self::REQUEST];
    }

    public function server(): array
    {
        return $this->data[self::SERVER];
    }


    public function config(string|null $key = null): string|array|Config
    {
        if (isset($this->data[self::CONFIG][$key])) {
            return $this->data[self::CONFIG][$key];
        } else {
            if ($key !== null) {
                throw new \Exception('Config Key missing: ' . $key);
            }
            return $this->data[self::CONFIG];
        }
    }

    public function hasUser(): bool
    {
        if (Config::isCli()) {
            return (bool)$this->request()->param('sessionuser', false, true);
        }
        if (isset($_SESSION)) {
            return (bool)Core::getValue('user', $_SESSION, false, true);
        }
        return false;
    }

    public function user(bool $hashed = true): string
    {
        $user = '';
        if (Config::isCli()) {
            $user = $this->data[self::REQUEST]->param('sessionuser');
        }
        if (!$user && isset($_SESSION)) {
            $user = Core::getValue('user', $_SESSION);
        }
        if ($hashed && $user) {
            $user = md5($user);
        }
        if (!$user) {
            if (Config::isCli()) {
                Core::echoOn();
                Core::echo(Colors::get('[error]', Colors::FG_red), 'Missing session user!', 'use param -sessionuser="a username" to simmulate a session');
                Core::echoReset($this);
            }
            throw new \Exception('Missing session user!');
        }
        return $user;
    }

    public function password(): string
    {
        if (Config::isCli()) {
            return $this->data[self::REQUEST]->param('sessionpass');
        } else {
            if (isset($_SESSION)) {
                return Core::getValue('password', $_SESSION);
            }
        }
        return '';
    }

    public function session(string $key = '', string|null $value = null, string $default = ''): string
    {
        $out = $default;
        if (isset($_SESSION)) {
            if ($value !== null) {
                if (!headers_sent()) {
                    session_start();
                }
                $_SESSION[$key] = $value;
//                Core::echo(__METHOD__, 'session save', $key, $value, $default,session_status());
                session_write_close();
            }
//            Core::echo(__METHOD__, 'session',$_SESSION, $key, get_debug_type($value),$value, $default);
            $out = Core::getValue($key, $_SESSION, $default);
        } else {
            Core::echo(__METHOD__, 'no session found', $key, $value, $default);
        }
        return $out;
    }

    public function setLanguage(array $allowedlanguages = [], string $default = 'de', string $query = 'lang'): string
    {
        $lang = $this->request()->vars($query, $default);
        if (in_array($lang, $allowedlanguages)) {
            $this->session('language', $lang, $default);
            $this->language = $lang;
            return $lang;
        }
        return '';
    }

    public function language(): string
    {
        return $this->language;
    }


    public function events(): Events
    {
        return Events::getInstance($this);
    }

    public function __clone(): void
    {
        $this->data[self::REQUEST] = clone $this->data[self::REQUEST];
        $this->data[self::RESPONSE] = clone $this->data[self::RESPONSE];
    }


}
