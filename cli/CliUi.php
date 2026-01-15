<?php

//declare(strict_types=1);

namespace cryodrift\fw\cli;


use cryodrift\fw\Config;
use cryodrift\fw\Core;
use cryodrift\fw\tool\Cli;

class CliUi
{
    static string $laststr = '';

    /**
     * /**
     * @param array $list
     * @param $footer
     * @return mixed
     */
    public static function showPageableMenu(array $list, array $exitcodes = array(), $header = '')
    {
        $choice = '';
        $len = 10;
        $pos = 0;
        $cnt = count($list);
        $paging = array(
          'N' => array(),
          'A' => array('n' => 'next'),
          'M' => array('n' => 'next', 'p' => 'prev'),
          'E' => array('p' => 'prev')
        );
        $typ = 'N';

        while (!is_numeric($choice)) {
            switch ($choice) {
                case'n':
                    if ($pos + $len <= $cnt) {
                        $pos += $len;
                    }
                    break;
                case'p':
                    if ($pos - $len >= 0) {
                        $pos -= $len;
                    }
                    break;
                default:
                    if ($exitcodes) {
                        if (array_key_exists($choice, $exitcodes)) {
                            return $choice;
                        }
                    }
            }
            if ($pos + $len < $cnt) {
                $typ = 'M';
            }
            if ($pos == 0 && $len < $cnt) {
                $typ = 'A';
            }
            if ($pos > 0 && $pos + $len >= $cnt) {
                $typ = 'E';
            }
            $shortlist = array_values(array_slice($list, $pos, $len, false));
            $correctlist = array_slice($list, $pos, $len, true);

            // header
            echo $header;
            // menu
            $choice = self::showMenu($shortlist + $paging[$typ] + $exitcodes, true, 1);
        }

        $ret = array_slice($correctlist, $choice, 1, true);

        return key($ret);
    }

    /**
     * @param bool $singleKey
     * @param int $minchars
     * @return null|string
     */
    public static function readKeys($singleKey = false, $minchars = 1): string|null
    {
        # do not use STDIN it buffers and no-one knows how to clear it
        if ($singleKey) {
            // liest ein zeichen und gibt es an php sofort weiter, fgetc wartet da auf Enter
            // da frag ich mich wieder warum es das nicht direct in php gibt ?
            // von stackoverflow
            if (Core::isUnix()) {
                $input = strtolower(trim(`bash -c "read -n $minchars ANS ; echo \\\$ANS"`));
            } else {
                $input = self::readWindowsKey();
            }
            echo PHP_EOL;
        } else {
            $stdin = fopen('php://stdin', 'r');
            $input = fgets($stdin);
            fclose($stdin);
        }
        // trim CR/LF from Enter
        $input = trim($input);
        if (strlen($input) == 0) {
            return null;
        }
        return $input;
    }

    public static function readWindowsKey(): string
    {
        $choices = '1234567890abcdefghijklmnopqrstuvwxyz';
        system('@choice /C ' . $choices . ' > nul', $errorlevel);
        $chars = str_split($choices, 1);
        return $chars[$errorlevel - 1];
    }

    /**
     * @param array $list
     * @param bool $single
     * @param int $chars
     * @param string $footer
     * @param bool $multi
     * @return array|int|null|string
     */
    public static function showMenu(array $list, $single = true, $chars = 1, $footer = '-------------------------------', $multi = false)
    {
        foreach ($list as $key => $value) {
            echo '[' . $key . '] ' . $value . PHP_EOL;
        }
        echo $footer . PHP_EOL;

        do {
            echo $multi ? 'multiple choice:' : 'choose:';
            $loop = false;
            $key = self::readKeys($single, $chars);
            if (!$multi) {
                $loop = !array_key_exists($key, $list);
            } else {
                $f = array_flip(explode(' ', $key));
                $extracted = array_intersect_key($list, $f);

                if (count($extracted) != count($f)) {
                    $loop = true;
                }
            }
        } while ($loop);
        echo PHP_EOL;
        if ($multi) {
            $key = explode(' ', $key);
        }
        return $key;
    }

    public static function askFor(string $name, bool $singlekey = false): string
    {
        echo 'provide ' . $name . PHP_EOL;
        return self::readKeys($singlekey);
    }


    public static function readEditor(string $currentdata = ''): string
    {
        $file = sys_get_temp_dir() . '/readEditor_' . md5(microtime()) . '.txt';
        Core::fileWrite($file, $currentdata);
        // start nano editor with
        system('nano ' . escapeshellarg($file) . ' > /dev/tty < /dev/tty');
        $currentdata = trim(file_get_contents($file));
        unlink($file);
        return $currentdata;
    }

    public static function echoBlock(string $text = '', string $design = '#'): void
    {
        self::echoLine(str_pad($design, strlen($text), $design) . $design . $design, $design);
        self::echoLine($text . ' ' . $design, $design);
        self::echoLine(str_pad($design, strlen($text), $design) . $design . $design, $design);
    }

    public static function echoLine(string $text = '', string $design = '#'): void
    {
        echo $design . ' ' . $text . PHP_EOL;
    }

    /**
     * temporary message which will be overriden with next tmpMsg
     * @param string $text
     */
    public static function echoTmpMsg(string $text = '', bool $append = false): void
    {
        if ($append) {
            echo $text;
            self::$laststr .= $text;
        } else {
            if ($text == self::$laststr) {
                return;
            }
            if (self::$laststr && !$text) {
                echo "\r" . str_repeat(' ', strlen(self::$laststr)) . "\r";
                self::$laststr = '';
            }

            if ($text) {
                echo "\r" . str_pad($text, strlen(self::$laststr), ' ', STR_PAD_RIGHT);
                self::$laststr = $text;
            }
        }
    }

    /**
     * @param $text
     * @param null $len
     */
    public static function echoTmpMsgModify(string $text, int|null $len = null)
    {
        if ($len === null) {
            $len = strlen($text);
        }
        $nt = substr(self::$laststr, 0, ($len * -1)) . $text;
        self::echoTmpMsg($nt);
    }

    /**
     * @param \Generator|iterable $list
     * @param callable $fnk
     * @param int $cnt
     * @return void
     * @TODO return a generator if callable is null, but typehinting in foreach is not great
     */
    public static function withProgressBar(\Generator|iterable $list, callable $fnk, int $cnt = 0): void
    {
        if (is_array($list)) {
            $cnt = count($list);
        }

        if ($cnt === 0 && $list instanceof \Generator) {
            throw new \BadMethodCallException('Argument $cnt is 0 but $list is Generator');
        }

        if ($cnt === 0 && $list instanceof \Iterator) {
            $cnt = iterator_count($list);
        }

        $len = 20;
        CliUi::echoTmpMsg('0 ' . str_pad('', $len, '_') . $cnt);
        $i = 0;

        foreach ($list as $k => $item) {
            $i++;
            //TODO use yield here
            $fnk($item, $k);
            $progress = $i / $cnt;
            $filledLength = (int)round($len * $progress);
            if (Config::isCli()) {
                Core::echoOn();
            }
            CliUi::echoTmpMsg(' ' . round($progress * 100) . '% ' . str_pad(str_pad('', $filledLength, '#'), $len, '_') . ' ' . $i . '/' . $cnt);
            if (Config::isCli()) {
                ob_start();
            }
        }
        CliUi::echoLine(PHP_EOL . 'Finished in ' . Core::time());
    }


    /**
     * @info get cli param value by param name
     * @info get cli param by position
     */
    public static function param(string|int $name, string $default = '', string $delim = '='): string
    {
        if (is_int($name)) {
            return Core::getValue($name, $_SERVER['argv'], $default);
        }
        foreach ($_SERVER['argv'] as $key => $value) {
            if ($delim === ' ') {
                if ($name == $value) {
                    return Core::getValue($key + 1, $_SERVER['argv'], $default);
                }
            } else {
                if (str_contains($value, $delim)) {
                    $parts = explode($delim, $value, 2);
                    return Core::getValue(1, $parts, $default);
                }
            }
        }
        return $default;
    }

    public static function arrayToCli(array $data, int $level = 0, string $hcol = Colors::FG_light_green): string
    {
        // output buffer
        $out = '';

        // current indent
        $indent = str_repeat(' ', $level);

        foreach ($data as $key => $value) {
            // helper: scalar → string
            if (is_bool($value)) {
                $valStr = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $valStr = 'null';
            } elseif (is_scalar($value)) {
                $valStr = (string)$value;
            } else {
                $valStr = gettype($value);
            }

            // non-numeric key → heading
            if (!is_int($key)) {
                // colored heading
                $out .= $indent . Colors::get($key, $hcol) . PHP_EOL;

                // value ist Array → rekursiv tiefer
                if (is_array($value)) {
                    $out .= self::arrayToCli($value, $level + 1, $hcol);
                } else {
                    // einfache Zeile unter der Überschrift
                    $out .= str_repeat(' ', $level + 1) . $valStr . PHP_EOL;
                }
            } else {
                // numerischer Key → einfache Liste
                if (is_array($value)) {
                    $out .= self::arrayToCli($value, $level, $hcol);
                } else {
                    $out .= $indent . $valStr . PHP_EOL;
                }
            }
        }

        return $out;
    }

}
