<?php

declare(strict_types=1);

$web = 'index.php';

if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', false)) {
    include 'phar://' . __FILE__ . '/' . $web;
}
__HALT_COMPILER(); ?>
