<?php

//declare(strict_types=1);

namespace cryodrift\fw\tool;

use cryodrift\fw\interface\Configs;
use cryodrift\fw\trait\CliDefaults;
use Exception;
use Generator;
use Phar;
use PharFileInfo;
use ReflectionClass;
use SplFileInfo;
use cryodrift\fw\cli\CliUi;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\interface\Installable;
use cryodrift\fw\interface\Testable;
use cryodrift\fw\Main;
use cryodrift\fw\trait\CliHandler;
use ZipArchive;

class Cli implements Handler, Configs
{
    use CliHandler;
    use CliDefaults;


    public function __construct(protected string $pharname, protected array $mounted, private readonly Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        $this->defaultsCli($ctx, Core::getValue('clidefaults', $this->config, []));
        return $this->handleCli($ctx);
    }

    /**
     * @cli create main config.php in root by merging src/.../config.php files
     * @cli param: -mode (web|cli)
     * @cli param: -write (writes the config cache)
     * @cli param: [-dir] (default src)
     */
    protected function config(string $mode, string|array $dir = 'src', bool $write = false): array
    {
        $out = [];
        if (in_array($mode, ['web', 'cli'])) {
            Config::setSapi($mode);
            $ctx = Core::newContext(
              new Config(
                include Main::path(Config::$baseconfig)
              )
            );

            if (!is_array($dir)) {
                $dir = [$dir];
            }

            foreach ($dir as $path) {
                foreach (Core::dirList(Main::path($path, true), fn(SplFileInfo $file) => $file->isDir() || $file->getBasename() == 'config.php') as $config) {
                    include $config->getPathname();
                }
                foreach (Core::dirList(Main::path($path), fn(SplFileInfo $file) => $file->isDir() || $file->getBasename() == 'config.php') as $config) {
                    include $config->getPathname();
                }
            }

            if (file_exists('cfg')) {
                foreach (Core::dirList('cfg', fn(SplFileInfo $file) => $file->isDir() || str_contains($file->getBasename(), 'config.php')) as $config) {
                    include $config->getPathname();
                }
            }
            if ($write) {
                $pathname = match ($mode) {
                    'web' => Main::$rootdir . Config::$datadir . Config::$webconfig,
                    'cli' => Main::$rootdir . Config::$datadir . Config::$cliconfig
                };
                Core::echo(__METHOD__, Colors::get('[written] ' . $pathname, Colors::FG_green));
                Core::fileWrite($pathname, '<?php' . PHP_EOL . ' return ' . var_export($ctx->config()->getArrayCopy(), true) . ';', 0, true);
            }
            Config::setSapi(PHP_SAPI);
            $out = $ctx->config()->getArrayCopy();
        }
        return $out;
    }

    /**
     * @cli install create .env, dirs, configs, vendor, ...
     * @cli param: [-dir] (src)
     * @cli param: [-modules] (run src/ cli installers)
     * @cli param: [-composer] (run composer require)
     * @cli param: [-npm] (run npm install)
     * @cli param: [-files] (download files and zips to vendor)
     * @cli param: [-a] (all above)
     */
    protected function install(Context $ctx, string|array $dir = 'src', bool $a = false, bool $modules = false, bool $composer = false, bool $npm = false, bool $files = false): array
    {
        $out = [];
        if ($a) {
            $npm = $composer = $files = $modules = true;
        }
        Core::dirCreate(Config::$datadir . 'logs', false);
        Core::dirCreate(Config::$datadir . 'users', false);
        $out['env'] = $this->env('write', $dir);
        $out['webconfig'] = $this->config('web', $dir, true);
        $out['cliconfig'] = $this->config('cli', $dir, true);
        if ($composer) {
            $out['composer'] = $this->composer('write', $dir);
        }
        if ($npm) {
            $out['npm'] = $this->npm('write', $dir);
        }
        if ($files) {
            $out['down'] = $this->down('write', $dir);
            $out['zip'] = $this->zip('write', $dir);
            $out['git'] = $this->git('write', $dir);
        }

        // run src/app.installers to create dirs and dbs .data/users
        // loop over src/ find Cli.php run::install()
        if ($modules) {
            $out['modules'] = $this->modules($ctx, $dir);
        }
        return $out;
    }

    /**
     * @cli run module installers
     * @cli param: [-dir=""] (module dir)
     */
    protected function modules(Context $ctx, string|array $dir = 'src'): array
    {
        $out[] = 'Modules';
        $dirs = is_array($dir) ? $dir : [$dir];
        foreach ($dirs as $d) {
            $srcdir = Main::path($d);
            foreach (Core::dirList($srcdir) as $file) {
                if ($file->getBasename() === 'Cli.php') {
                    $cliclass = trim(str_replace('/', '\\', Core::getNamespace($file->getPathname())), '\\') . '\\Cli';
                    $classinfo = new ReflectionClass($cliclass);
                    if ($classinfo->implementsInterface(Installable::class)) {
                        try {
                            $out[Core::toLog(Colors::get('[Start]', Colors::FG_light_cyan), $cliclass)] = Core::newObject($cliclass, $ctx)->install(clone $ctx);
                            $out[Core::toLog(Colors::get('[Done]', Colors::FG_green), $cliclass)] = '';
                        } catch (Exception $ex) {
                            $out[Core::toLog(Colors::get('[Error]', Colors::FG_red), $cliclass)] = Core::toLog($ex->getMessage());
                        }
                    } else {
                        $out[Colors::get('[Ignore]', Colors::FG_yellow)] = $cliclass;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @cli run module tests
     * @cli param: [-dir=""] (module dir)
     */
    protected function tests(Context $ctx, string $dir = 'src'): array
    {
        $out[] = 'Tests';
        $srcdir = Main::path($dir);
        foreach (Core::dirList($srcdir) as $file) {
            if ($file->getBasename() === 'Cli.php') {
                $cliclass = trim(str_replace('/', '\\', $file->getPath()), '\\') . '\\Cli';
                $classinfo = new ReflectionClass($cliclass);
                if ($classinfo->implementsInterface(Testable::class)) {
                    $out[$cliclass] = Core::newObject($cliclass, $ctx)->test(clone $ctx);
                }
            }
        }
        return $out;
    }

    /**
     * @cli install composer libs
     * @cli param -mode="(write|show)"
     * @cli param [-dir]=""
     * @cli param [-remove] (remove all packages)
     */
    protected function composer(string $mode = 'show', string|array $dir = 'src', bool $remove = false): string
    {
        $out = [];
        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                $out = array_merge($out, Core::iterate($this->configMeta($d, 'composer'), function (Configmeta $data) use ($mode, $remove) {
                    if ($mode === 'write') {
                        $this->requirePackage($data->url, $remove);
                    } else {
                        return $data->name . ': ' . $data->url;
                    }
                    return '';
                }));
            }
        }
        return Core::toLog(__METHOD__, $out);
    }

    /**
     * @cli install npm libs
     * @cli param -mode="(write|show)"
     * @cli param [-dir]=""
     * @cli param [-remove] (remove all packages)
     */
    protected function npm(string $mode = 'show', string|array $dir = 'src', bool $remove = false): string
    {
        $out = [];
        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                $out = array_merge($out, Core::iterate($this->configMeta($d, 'npm'), function (Configmeta $data) use ($mode, $remove) {
                    if ($mode === 'write') {
                        $this->installNpmPackage($data->url, $remove);
                    }
                    return $data->name . ': ' . $data->url;
                }));
            }
        }
        return Core::toLog(__METHOD__, $out);
    }

    /**
     * @cli download files to vendor/...
     * @cli use @down url/name.ext in your config.php
     * @cli param -mode="(write|show)"
     * @cli param [-dir]="" (default: src/)
     */
    protected function down(string $mode = 'show', string|array $dir = 'src'): string
    {
        $out = [];
        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                $out = array_merge($out, Core::iterate($this->configMeta($d, 'down'), function (Configmeta $data) use ($mode) {
                    if ($mode === 'write') {
                        $pathname = $data->pathname;
                        Core::dirCreate($pathname);
                        file_put_contents($pathname, file_get_contents($data->url));
                    }
                    return '';
                }));
            }
        }
        return Core::toLog(__METHOD__, $out);
    }

    /**
     * @cli download and extract zipfiles to vendor/...
     * @cli use @zip url/name.zip in your config.php
     * @cli param -mode="(write|show)"
     * @cli param [-dir]=""
     */
    protected function zip(string $mode = 'show', string|array $dir = 'src'): string
    {
        $out = [];
        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                $out = array_merge($out, Core::iterate($this->configMeta($d, 'zip'), function (Configmeta $data) use ($mode) {
                    if ($mode === 'write') {
                        $pathname = $data->pathname . '/';
                        Core::dirCreate($pathname, false);
                        $file = file_get_contents($data->url);
                        file_put_contents($data->tmpfile, $file);
                        $zip = new ZipArchive();
                        if ($zip->open($data->tmpfile, ZipArchive::RDONLY) === true) {
                            $zip->extractTo($pathname);
                            $zip->close();
                            return 'Das Entpacken war erfolgreich!';
                        } else {
                            return 'Es gab einen Fehler beim Öffnen der ZIP-Datei.';
                        }
                    }
                    return 'Nothing done';
                }));
            }
        }
        return Core::toLog(__METHOD__, $out);
    }

    /**
     * @cli git clone into vendor/...
     * @cli use @git url in your config.php
     * @cli param -mode="(write|show)"
     * @cli param [-dir]=""
     * @cli param [-override] (move existing repo to temp)
     */
    protected function git(string $mode = 'show', string|array $dir = 'src', bool $override = false): string
    {
        $out = [];

        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                $out = array_merge($out, Core::iterate($this->configMeta($d, 'git'), function (Configmeta $data) use ($mode, $override) {
                    if ($mode === 'write') {
                        $pathname = $data->pathname . '/';
                        if ($override && is_dir($data->pathname)) {
                            rename($data->pathname, $data->tmpfile . md5(time()));
                        }
                        Core::dirCreate($pathname, false);
                        $command = 'git clone --depth=1 ' . escapeshellarg($data->url) . ' ' . escapeshellarg($pathname);
                        exec($command, $output, $code);
                        return 'cloned into ' . $pathname;
                    }
                    return '';
                }));
            }
        }
        return Core::toLog(__METHOD__, $out);
    }

    /**
     * @cli create/modify .env file from all config.php
     * @cli param -mode="(write|show)"
     * @cli param [-dir]=""
     *
     */
    protected function env(string $mode = 'show', string|array $dir = 'src'): string
    {
        $currentenv = [];
        if (file_exists(Config::$envfile)) {
            $currentenv = parse_ini_file(Config::$envfile, false, INI_SCANNER_TYPED);
        }
        Core::echo(__METHOD__, 'current env:', $currentenv);

        if (in_array($mode, ['write', 'show'])) {
            $dirs = is_array($dir) ? $dir : [$dir];
            foreach ($dirs as $d) {
                foreach ($this->configVars($d, 'env') as $name => $vars) {
                    if (!empty($vars)) {
                        $data = parse_ini_string(implode(PHP_EOL, $vars), false, INI_SCANNER_TYPED);
                        Core::echo(__METHOD__, 'module:', $name, $vars);
                        foreach ($data as $key => $value) {
                            if (!array_key_exists($key, $currentenv)) {
                                $newval = $value;
                                if (is_string($newval) && in_array($newval, ['true', 'false', '0', '1'])) {
                                    $newval = (bool)$newval;
                                }
                                if (is_string($newval) && is_numeric($newval)) {
                                    $newval = (int)$newval;
                                }
                                $currentenv[$key] = str_replace('G_ROOTDIR', Main::$rootdir, $newval);
                            }
                        }
                    }
                }
            }
        }
        $out = '';
        foreach ($currentenv as $key => $value) {
//            Core::echo(__METHOD__, $key, gettype($value), $value);
            $val = match (gettype($value)) {
                'boolean' => $value ? 'true' : 'false',
                default => '"' . $value . '"'
            };
            $out .= $key . '=' . $val . PHP_EOL;
        }
        Core::echo(__METHOD__, 'modified env:');
        if ($mode === 'write') {
            file_put_contents(Config::$envfile, $out);
        }
        return $out;
    }

    /**
     * @cli start local server
     * @cli param [-port]= (default: 8989)
     * @cli param [-ip]= (default: localhost)
     */
    protected function serv(string $port = '8989', string $ip = 'localhost'): int
    {
        $index = G_PHARFILE ?: 'index.php';
        $command = 'php -S ' . $ip . ':' . $port . ' ' . $index;

        $descriptorspec = [
          0 => ["pipe", "r"],
          1 => ["file", "php://stdout", "w"],
          2 => ["file", "php://stderr", "w"]
        ];
        $process = proc_open($command, $descriptorspec, $pipes);
        if (is_resource($process)) {
            $code = proc_close($process);
        }
        return $code;
    }

    /**
     * @cli show system wide help and examples
     */
    public function help(Context $ctx): Context
    {
        $index = G_PHARFILE ?: 'index.php';
        $data = [
          Colors::get('Help', Colors::FG_green) =>
            [
              Colors::get('Global optional params', Colors::FG_green) => [
                '-echo (display all echos and timings)',
                '-debug (dont render output)',
                '-debug2 (show only echos)',
                '-debug3 (show params, pathparts, vars)',
                '-debughide="key key key" (hide keys in HtmlUi dump)',
                '-sessionuser="username" (simulate user)',
                '-sessionuser="username" (simulate user)',
                ' -sessionpass[="password"] (if empty prompt for password)',
              ],
                //TODO get this from the installed packages eg. globalhelp config
              Colors::get('Special optional param for package', Colors::FG_green) => [
                'fakepost: -post[="fakepostfile.txt"] (simulate post request)',
                'CliHandler: -help (dont run cli but show allowed params)',
                'Routes: -verbose',
              ],
              Colors::get('Examples', Colors::FG_green) => [
                'php ' . $index . '',
                'php ' . $index . ' -verbose',
                'php ' . $index . ' /sys -echo',
                'php ' . $index . ' -debug "http://localhost:8080/chat/index?queryparam=blabla" ',
                'php ' . $index . ' "/chat/api/save?message=blabla" -post -debug',
                'php ' . $index . ' " -echo /sys install -a',
                'php ' . $index . ' " -echo /sys pharcreate -help',
                'php ' . $index . ' " -echo /sys pharadd -path="src/..." -path="sys" -path="src/chat"',
              ],
            ]
        ];

        $data = array_merge($ctx->response()->getData(), $data);
        $data = CliUi::arrayToCli($data, 1);
        $ctx->response()->setData([]);
        $ctx->response()->setContent(Core::toLog($data));
        return $ctx;
    }

    /**
     * @cli create .phar
     * @cli param: [-sys] (default: true, add dir sys/ )
     * @cli param: [-name] (output filename, default: from config )
     */
    protected function pharcreate(bool $sys = true, string $name = ''): string
    {
        if (Phar::running()) {
            return 'not possible in phar mode';
        }
        $pharfile = $name ?: $this->pharname;
        if (file_exists($pharfile)) {
            unlink($pharfile);
        }
        $phar = new Phar($pharfile, 0, $pharfile);
        if ($sys) {
            $this->addDir('sys/', $phar);
        }
        $phar->addFile('index.php', 'index.php');
        $phar->addFile(Config::$baseconfig, Config::$baseconfig);
        $phar->setStub(file_get_contents(Main::path(__DIR__ . '/pharstub.php')));
        return $pharfile . ' created';
    }

    /**
     * @cli add file or dir to phar
     * @cli param: -path=(relative dirname or filename) multi -path allowed
     * @cli param: [-name] (output filename, default: from config )
     *
     */
    protected function pharadd(array|string $path, string $name = ''): string
    {
        $pharfile = $name ?: $this->pharname;
//        $pharfile = Main::path($this->pharname);
        if ($pharfile) {
            $phar = new Phar($pharfile, 0, $pharfile);
            if (is_string($path)) {
                $path = [$path];
            }
            foreach ($path as $p) {
                if (is_dir($p)) {
                    $this->addDir($p, $phar);
                } else {
                    $this->addFile($p, $phar);
                }
            }
        }
        $out = Core::toLog($pharfile, ' added dir: ', $path);
        return $out;
    }

    /**
     * @cli show files in phar
     * @cli param: [-name] (output filename, default: from config )
     */
    protected function pharshow(string $name = ''): array
    {
        $pharfile = $name ?: $this->pharname;
        if (file_exists($pharfile) || $pharfile = Phar::running(false)) {
            $phar = new Phar($pharfile);

//                    $data = new RecursiveIteratorIterator($phar->getChildren(), RecursiveIteratorIterator::CATCH_GET_CHILD);
            $mounted = $this->mounted;
            array_pop($mounted);
            $data = Core::dirList($phar, function (PharFileInfo $current) use ($mounted) {
                if ($current->getType() === 'dir' && in_array($current->getBasename(), $mounted)) {
                    return false;
                }
                return true;
            });
            $root = strtr(strtolower('phar://' . realpath($pharfile)), '\\', '/');

            $out = Core::iterate($data, function (PharFileInfo $file) use ($root) {
                return str_replace($root, '', strtolower($file->getPathName()));
            });
        }
        return $out;
    }

    /**
     * @cli phar extract dirs or files
     * @cli param -path="" (dir or file to extract from phar)
     * @cli param [-dest]="" (default: .data/extracted)
     * @cli param [-override] (default: false)
     * @cli param: [-name] (output filename, default: from config )
     */
    protected function pharextract(string $path, string $name = '', string $dest = '', bool $override = false): array
    {
        if (empty($dest)) {
            $dest = Config::$datadir . 'extracted';
        }
        $out = [];
        $pharfile = $name ?: $this->pharname;
//        $pharfile = Main::path($this->pharname);
        if (is_dir($path)) {
            $path = trim($path, '/\\') . '/';
        }
        if ($pharfile) {
            try {
                $phar = new Phar($pharfile, 0, $this->pharname);
                $phar->extractTo($dest, $path, $override);
                $out[] = 'Extraction Done!';
            } catch (Exception $ex) {
                if (str_contains($ex->getMessage(), ' path already exists')) {
                    $this->missingparam = 'Extract Failed! use param -override ';
                }
            }
        }
        return $out;
    }


    /**
     * @cli show global vars and constants
     * @cli param: [-env] (show $_ENV)
     * @cli param: [-server] (show $_SERVER)
     * @cli param: [-const] (show defined constants)
     */
    protected function vars(bool $env = false, bool $server = false, bool $const = false): string
    {
        $out = [
          'include_path' => get_include_path(),
          'Phar::running()' => Phar::running(),
          'Phar::running(false)' => Phar::running(false),
          'Main::$rootdir' => Main::$rootdir,
          'Config::$logdir' => Config::$logdir,
          'Config::$datadir' => Config::$datadir,
          'Config::$cliconfig' => Config::$cliconfig,
          'Config::$webconfig' => Config::$webconfig,
          'current working dir' => getcwd(),
          'Config::$includedirs' => Config::$includedirs,
        ];
        if ($server) {
            $out['SERVER'] = $_SERVER;
        }
        if ($env) {
            $out['ENV'] = $_ENV;
        }
        if ($const) {
            $out['constants'] = get_defined_constants(true);
        }
        return Core::toLog($out);
    }


    private function addDir(string $dir, Phar $phar): void
    {
        $phar->startBuffering();
//        $regex = '/\/' . preg_quote($dir, '/') . '\/.*/';
//        $phar->buildFromDirectory('.',$regex);
        CliUi::withProgressBar(Core::dirList($dir), function (SplFileInfo $file) use ($dir, $phar) {
            $this->addFile($file->getPathname(), $phar);
        });
        $phar->stopBuffering();
    }

    private function addFile(string $pathname, Phar $phar): void
    {
        $destpath = str_replace([Main::$rootdir, '\\'], ['', '/'], $pathname);
        $phar->addFile($pathname, $destpath);
    }

    private function configVars(string $dir, string $varname): Generator
    {
        foreach (Core::dirList($dir, fn(SplFileInfo $file) => $file->isDir() || $file->getBasename() === 'config.php') as $config) {
            if ($config->isDir()) {
                continue;
            }
            $comment = '';
            $data = Core::fileReadOnce($config->getPathname());
            foreach (token_get_all($data) as $token) {
                if (is_array($token) && ($token[0] === T_DOC_COMMENT)) {
                    $comment = $token[1];
                    break;
                }
            }
            $vars = Core::getValue($varname, Core::getDocCommentVars($comment), []);
            yield $config->getPathname() => $vars;
        }
    }

    private function requirePackage(string $name, bool $remove = false): void
    {
        $mode = 'require';
        if ($remove) {
            $mode = 'remove';
        }
        $command = 'composer ' . $mode . ' ' . escapeshellarg($name) . ' --no-interaction --ignore-platform-reqs';
        exec($command, $output, $code);
        Core::echo(__METHOD__, $output, $code);
    }

    private function installNpmPackage(string $name, bool $remove = false): void
    {
        $mode = 'i';
        if ($remove) {
            $mode = 'r';
        }
        $command = 'npm ' . $mode . ' ' . escapeshellarg($name);
        exec($command, $output, $code);
        Core::echo(__METHOD__, $output, $code);
    }

    private function configMeta(string $dir, string $typ, string $dest = 'vendor/'): Generator
    {
        foreach ($this->configVars($dir, $typ) as $name => $vars) {
            if (!empty($vars)) {
                $name = str_replace(['\\', $dir . '/', Phar::running()], ['/', '', ''], dirname($name));
                foreach ($vars as $url) {
                    $pathname = Main::$rootdir . $dest . trim($name, '/') . '/' . basename($url);
                    $tmpfile = sys_get_temp_dir() . '/' . basename($url);
                    $data = new Configmeta($name, $pathname, $url, $tmpfile);
                    Core::echo(__METHOD__, $data);
                    yield $data;
                }
            }
        }
    }

    /**
     * @cli copy files/dirs into one destination directory
     * @cli param: -source= (string) can be specified multiple times
     * @cli param: -dest= (string) destination directory
     * @cli param: [-newer] (default: true) copy only newer files
     * @cli param: [-mode] ('show' or 'write', default: 'show')
     */
    protected function copydir(array $source, string $dest, bool $newer = true, string $mode = 'show'): string
    {
        $ops = [];
        $counts = ['scanned' => 0, 'copied' => 0, 'skipped' => 0, 'errors' => 0];
        // normalize destination dir and ensure it exists (on write)
        $dest = rtrim($dest, "\\/");
        if ($mode === 'write') {
            Core::dirCreate($dest, false);
        }
        foreach ($source as $src) {
            if (!$src) {
                continue;
            }
            if (is_dir($src)) {
                $srcbase = rtrim($src, "\\/");
                foreach (Core::dirList($srcbase) as $file) {
                    /** @var SplFileInfo $file */
                    if ($file->isDir()) {
                        continue;
                    }
                    $rel = substr($file->getPathname(), strlen($srcbase) + 1);
                    $target = $dest . DIRECTORY_SEPARATOR . $rel;
                    $counts['scanned']++;
                    $ops[] = [$file->getPathname(), $target];
                }
            } elseif (is_file($src)) {
                $target = $dest . DIRECTORY_SEPARATOR . basename($src);
                $counts['scanned']++;
                $ops[] = [$src, $target];
            } else {
                // allow glob patterns for convenience
                $matches = glob($src, GLOB_BRACE);
                if ($matches) {
                    foreach ($matches as $m) {
                        if (is_dir($m)) {
                            $srcbase = rtrim($m, "\\/");
                            foreach (Core::dirList($srcbase) as $file) {
                                /** @var SplFileInfo $file */
                                if ($file->isDir()) {
                                    continue;
                                }
                                $rel = substr($file->getPathname(), strlen($srcbase) + 1);
                                $target = $dest . DIRECTORY_SEPARATOR . $rel;
                                $counts['scanned']++;
                                $ops[] = [$file->getPathname(), $target];
                            }
                        } elseif (is_file($m)) {
                            $target = $dest . DIRECTORY_SEPARATOR . basename($m);
                            $counts['scanned']++;
                            $ops[] = [$m, $target];
                        }
                    }
                }
            }
        }

        // Execute or show operations
        $log = [];
        foreach ($ops as [$srcpath, $dstpath]) {
            $doCopy = true;
            if ($newer && file_exists($dstpath)) {
                $doCopy = (filemtime($srcpath) > filemtime($dstpath));
            }
            $action = $doCopy ? ($mode === 'write' ? 'COPY' : 'WOULD COPY') : 'SKIP';
            $log[] = Core::toLog($action, $srcpath, '->', $dstpath);
            if ($doCopy && $mode === 'write') {
                try {
                    Core::dirCreate($dstpath, true);
                    if (!@copy($srcpath, $dstpath)) {
                        // fallback using Core::writeFile
                        $data = file_get_contents($srcpath);
                        Core::fileWrite($dstpath, $data);
                    }
                    $counts['copied']++;
                } catch (\Throwable $e) {
                    $counts['errors']++;
                    $log[] = Core::toLog('ERROR', $e->getMessage());
                }
            } else {
                $counts['skipped'] += ($doCopy ? 0 : 1);
            }
        }
        $summary = Core::toLog('copydir summary:', 'scanned', $counts['scanned'], 'copied', $counts['copied'], 'skipped', $counts['skipped'], 'errors', $counts['errors']);
        $log[] = $summary;
        return implode('', $log);
    }

    public static function addConfigs(Context $ctx, array $data, string $typ = ''): void
    {
        $config = $ctx->config(self::class);
        $config[$typ] = array_merge(Core::getValue($typ, $config, []), $data);
        $config[$typ] = array_unique($config[$typ]);
        $ctx->config()->setConfig(self::class, $config);
        Core::echo(__METHOD__, self::class, $config, $ctx->config(self::class));
    }
}
