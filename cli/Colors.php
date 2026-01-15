<?php
//declare(strict_types=1);
namespace cryodrift\fw\cli;

use cryodrift\fw\Config;
use cryodrift\fw\Core;

class Colors
{
    const FG_black = '0;30';
    const FG_dark_gray = '1;30';
    const FG_blue = '0;34';
    const FG_light_blue = '1;34';
    const FG_green = '0;32';
    const FG_light_green = '1;32';
    const FG_cyan = '0;36';
    const FG_light_cyan = '1;36';
    const FG_red = '0;31';
    const FG_light_red = '1;31';
    const FG_purple = '0;35';
    const FG_light_purple = '1;35';
    const FG_brown = '0;33';
    const FG_yellow = '1;33';
    const FG_light_gray = '0;37';
    const FG_white = '1;37';
    const BG_black = '40';
    const BG_red = '41';
    const BG_green = '42';
    const BG_yellow = '43';
    const BG_blue = '44';
    const BG_magenta = '45';
    const BG_cyan = '46';
    const BG_light_gray = '47';

    public static function get(string $string, string $foreground_color = '', string $background_color = ''): string
    {
        if (!Config::isCli()) {
            return $string;
        }
        $colored_string = "";
        if ($foreground_color) {
            $colored_string .= "\e[" . $foreground_color . "m";
        }
        if ($background_color) {
            $colored_string .= "\e[" . $background_color . "m";
        }
        // Add string and end coloring
        $colored_string .= $string . "\e[0m";
        return $colored_string;
    }

}
