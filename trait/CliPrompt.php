<?php

namespace cryodrift\fw\trait;

use cryodrift\fw\Config;
use cryodrift\fw\Core;

trait CliPrompt
{
    protected function cliprompt(bool $hidden = false): string
    {
        $out = '';
        if (Config::isCli()) {
            if (Core::isUnix()) {
                $out = $this->unixcli($hidden);
            } else {
                $out = $this->powershellcli2($hidden);
            }
        }
        return $out;
    }

    private function powershellcli(): string
    {
        return '';
    }

    private function powershellgui(): string
    {
        $pwCommand = 'powershell -NoProfile -Command ^
          "Add-Type -AssemblyName System.Windows.Forms; ^
           $form = New-Object System.Windows.Forms.Form; ^
           $form.Text = \'Enter Password\'; ^
           $tb = New-Object System.Windows.Forms.TextBox; ^
           $tb.UseSystemPasswordChar = \$true; ^
           $tb.Width = 200; ^
           $form.Controls.Add($tb); ^
           $ok = New-Object System.Windows.Forms.Button; ^
           $ok.Text = \'OK\'; $ok.Add_Click({ $form.Tag = $tb.Text; $form.Close() }); ^
           $form.Controls.Add($ok); ^
           $form.StartPosition = \'CenterScreen\'; ^
           $form.ShowDialog() | Out-Null; ^
           $form.Tag"';
        return trim(shell_exec($pwCommand));
    }

    private function unixcli(bool $hidden): string
    {
        if ($hidden) {
            system('stty -echo');
        }
        $out = trim(fgets(\STDIN));
        if ($hidden) {
            system('stty echo');
        }
        return $out;
    }

    private function powershellcli2(bool $hidden = false): string
    {
        $ansiMask = "\x1b[30;40m"; // black fg on black bg
        $ansiReset = "\x1b[0m";    // reset attributes
        // Visible input: just read from STDIN (characters will echo)
        if ($hidden) {
            echo $ansiMask;
        }

        // Hidden requested: set foreground and background to the same color during typing
        // Use ANSI escape sequences to set black foreground on black background, then reset.
        // Note: This relies on ANSI support (Windows 10+ terminals, most modern consoles). If unsupported,
        // the characters may still appear; we keep a post-read clear as a fallback.
        $line = fgets(\STDIN);
        $out = trim((string)$line);

        if ($hidden) {
            echo $ansiReset;
            // Fallback cleanup: try to clear any echoed content if masking wasn't effective
            $ansiClear = "\x1b[1A\x1b[2K"; // cursor up, erase line
            $restoreBelow = "\r"; // best-effort cursor management
            echo $ansiClear;
            echo "\x1b[2K" . $restoreBelow;
            $cols = 200; // conservative width
            echo "\r" . str_repeat(' ', $cols) . "\r";
        }

        return $out;
    }
}
