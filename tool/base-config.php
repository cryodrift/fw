<?php

//declare(strict_types=1);

/**
 * Handler config (Middleware)
 * the position matters
 */


/**
 * Router
 */
$data['Handler'][\cryodrift\fw\Router::class] = [
  \cryodrift\fw\Router::TYP_EMPTY => [[\cryodrift\fw\Router::class, 'routes'], [\cryodrift\fw\FileHandler::class, 'files'], [\cryodrift\fw\tool\Cli::class, 'help']],
  \cryodrift\fw\Router::TYP_CLI => [
    'sys' => \cryodrift\fw\tool\Cli::class,
  ],
  \cryodrift\fw\Router::TYP_WEB => [
    '/' => []
  ]
];

/**
 * Static File Routes
 */
$data['Handler'][\cryodrift\fw\FileHandler::class] = [
  'cacheDuration' => 60 * 60 * 24 * 7 * 10,
  'files' => [
    'system.js' => 'js/system.js',
    'customelements.js' => 'js/customelements.js',
    'styleslist.js' => 'js/styleslist.js',
    'dragdrop.js' => 'js/dragdrop.js',
    'scrollable.js' => 'js/scrollable.js',
    'scrollloader.js' => 'js/scrollloader.js',
    'dataloader.js' => 'js/dataloader.js',
    'eventhandler.js' => 'js/eventhandler.js',
    'tablisttools.js' => 'js/tablisttools.js',
    'modifyurls.js' => 'js/modifyurls.js',
    'favicon.ico' => 'favicon.ico',
    'robots.txt' => 'robots.txt'
  ]
];


/**
 *  dependency injection config
 */

$data[\cryodrift\fw\tool\Cli::class] = [
  'mounted' => \cryodrift\fw\Config::$pharmounts,
  'pharname' => 'cryodrift.phar',
  'clidefaults' => [
    'dir' => 'vendor/cryodrift',
  ]
];

$data[\cryodrift\fw\Events::class] = ['listeners' => []];


return $data;
