<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Core;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\Request;

trait CmdRunner
{

    /**
     * Runs a command with optional dry-run. If $write=false, only echo the command and do not execute.
     * Returns [exit_code, null]. If $command is array, escape arguments robustly.
     */
    protected function runCmd(array|string $command, ?string $cwd = null, bool $write = false): array
    {
        $cmd = $this->buildCommand($command);

        $prevCwd = $this->switchToCwd($cwd);

        // Print the command for visibility/debugging only when requested
        if ($this->shouldEcho()) {
            $tag = $write ? '[exec]' : '[dry]';
            Core::echo(Colors::get($tag, Colors::FG_light_gray) . ' ' . $cmd, $cwd ?? getcwd());
        }

        if (!$write) {
            $this->restoreCwd($prevCwd);
            return [0, null];
        }

        $exitCode = 0;
        // Suppress subprocess output unless echo/debug explicitly requested
        $quiet = !$this->shouldEcho();
        $cmdToRun = $cmd;
        if ($quiet) {
            $null = $this->isWindows() ? 'NUL' : '/dev/null';
            $cmdToRun .= ' > ' . $null . ' 2>&1';
        }
        if (function_exists('system')) {
            system($cmdToRun, $exitCode);
        } else {
            passthru($cmdToRun, $exitCode);
        }

        $this->restoreCwd($prevCwd);
        return [$exitCode, null];
    }

    protected function runCmdCapture(array|string $command, ?string $cwd = null, bool $write = false): array
    {
        // Same quoting logic as run(), but capture stdout/stderr via redirection to a temp file.
        $cmd = $this->buildCommand($command);

        $prevCwd = $this->switchToCwd($cwd);

        $tmpFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'extractcomp_' . uniqid('', true) . '.txt';
        $redir = $cmd . ' > ' . ($this->isWindows() ? '"' . str_replace('"', '\\"', $tmpFile) . '"' : escapeshellarg($tmpFile)) . ' 2>&1';

        if ($this->shouldEcho()) {
            $tag = $write ? Colors::get('[exec]', Colors::FG_light_green) : Colors::get('[dry]', Colors::FG_yellow);
            Core::echo($tag, 'in workdir:', $cwd ?? getcwd(), 'command:', $cmd);
        }

        if (!$write) {
            $this->restoreCwd($prevCwd);
            return [0, ''];
        }

        $exitCode = 0;
        if (function_exists('system')) {
            system($redir, $exitCode);
        } else {
            passthru($redir, $exitCode);
        }

        $output = '';
        if (is_file($tmpFile)) {
            $output = (string)@file_get_contents($tmpFile);
            @unlink($tmpFile);
        }

        $this->restoreCwd($prevCwd);
        return [$exitCode, $output];
    }


    /**
     * Decide whether to print command lines and let subprocess output pass through.
     * Enabled when CLI param -echo or -debug is present.
     */
    private function shouldEcho(): bool
    {
        $params = Request::getCliParams();
        return isset($params['echo']) || isset($params['debug']);
    }

    /**
     * True on Windows platforms (case-insensitive check against PHP_OS_FAMILY).
     */
    private function isWindows(): bool
    {
        return stripos(PHP_OS_FAMILY, 'Windows') !== false;
    }

    /**
     * Build a shell command string from an array of parts or a raw string.
     * Mirrors the quoting rules used previously in this trait.
     */
    private function buildCommand(array|string $command): string
    {
        if (is_array($command)) {
            $cmd = '';
            foreach ($command as $i => $part) {
                if ($part === null) {
                    $part = '';
                }
                if ($this->isWindows()) {
                    $quoted = '"' . str_replace('"', '\\"', (string)$part) . '"';
                    if ($i === 0 && preg_match('~^[^\s\"\']+$~', (string)$part)) {
                        $quoted = (string)$part;
                    }
                    $cmd .= ($cmd === '' ? '' : ' ') . $quoted;
                } else {
                    $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg((string)$part);
                }
            }
            return $cmd;
        }
        return (string)$command;
    }

    /**
     * Change to $cwd if provided; returns previous cwd (or null if unchanged).
     */
    private function switchToCwd(?string $cwd): ?string
    {
        $prevCwd = null;
        if ($cwd !== null && $cwd !== '') {
            $prevCwd = getcwd();
            chdir($cwd);
        }
        return $prevCwd;
    }

    /**
     * Restore previous working directory if provided.
     */
    private function restoreCwd(?string $prevCwd): void
    {
        if ($prevCwd !== null) {
            chdir($prevCwd);
        }
    }

}
