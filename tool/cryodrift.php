#!/usr/bin/env php
<?php
//declare(strict_types=1);
namespace cryodrift_;

use cryodrift\fw\Config;
use cryodrift\fw\Main;
use Phar;

// get autoload from upto 5 levels deep
for ($a = 1; $a <= 5; $a++) {
    $filename = dirname(__DIR__, $a) . '/autoload.php';
    if (file_exists($filename)) {
        require_once $filename;
        break;
    }
}

// Define runtime constants
if (!defined('G_PHARFILE')) {
    define('G_PHARFILE', basename(Phar::running()));
}
if (!defined('G_PHAR')) {
    define('G_PHAR', 'phar://' . G_PHARFILE . '/');
}
if (!defined('G_PHARROOT')) {
    define('G_PHARROOT', dirname(__DIR__, 4));
}

Main::$rootdir = dirname(Phar::running(false)) ? dirname(Phar::running(false)) . '/' : dirname(__DIR__, 4) . '/';
//allow overrides
Config::$includedirs = [
  '.',
  './',
  Main::$rootdir . 'src/',
  Main::$rootdir . 'sys/',
  Main::$rootdir,
  Main::$rootdir . 'vendor/cryodrift/fw/',
  Main::$rootdir . 'vendor/cryodrift/',
  G_PHARROOT . '/src/',
  G_PHARROOT . '/sys/',
  G_PHARROOT . '/',
];
set_include_path(implode(PATH_SEPARATOR, Config::$includedirs));

// Run in CLI mode
$config = Main::readConfig();
$out = Main::run($config);

// Exit code 0 for normal completion
if (Config::isCli()) {
    echo $out;
    exit(0);
} else {
    return $out;
}
