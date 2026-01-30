<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\cli\CliUi;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Param;


trait CliHandler
{
    use ReflectHelper;
    use OutHelper;

    protected string $missingparam = '';

    public function handleCli(Context $ctx, string $methodname = ''): Context
    {
        return $this->methodOrHelp(Core::getValue($methodname, [$methodname => $methodname], $ctx->request()->arg(2), true), $ctx);
    }

    private function methodOrHelp(string $methodname, Context $ctx): Context
    {
        $exists = false;
        $methodname = strtolower($methodname);
        $handlers = $this->reflectHandlers('cli');
        if (array_key_exists($methodname, $handlers)) {
            $req = $ctx->request();
            try {
                $exists = true;
                if (!$req->hasParam('help')) {
                    $parameters = $req->getParams();
                    $params = [];
                    foreach (Core::methodParams($this, $methodname) as $paraminfo) {
                        $value = Core::getParamValue($paraminfo, $parameters, $ctx);
//                    Core::echo(__METHOD__,$value,$paraminfo);
                        if (array_key_exists($paraminfo->name, $value)) {
                            // empty cli bool params are set to true for convenients
                            if (is_bool($paraminfo->type) && $req->hasParam($paraminfo->name) && !$req->param($paraminfo->name) && $req->param($paraminfo->name) !== '0') {
                                $params[$paraminfo->name] = true;
                            } else {
                                $params[$paraminfo->name] = $value[$paraminfo->name];
                            }
                        }
                    }
                    $out = $this->$methodname(...$params);
                    $ctx = $this->outHelper($out, $ctx);
                }
            } catch (\BadMethodCallException $ex) {
                $this->missingparam = $ex->getMessage();
            } catch (\ArgumentCountError $ex) {
                $this->missingparam = $ex->getMessage();
            } catch (\InvalidArgumentException $ex) {
                $this->missingparam = $ex->getMessage();
            }
        }

        if (!$ctx->response()->getContent()) {
            $help = 'Usage: ' . $ctx->request()->path()->getString() . ' command';
            // show some help
            if ($exists) {
                $hdata = Core::extractData($handlers, [$methodname]);
                $realparams = [];
                $mparams = Core::methodParams($this, $methodname);
                foreach ($mparams as $paraminfo) {
                    $type = $paraminfo->typestr;

                    if ($paraminfo->buildin || Core::hasInterface($type, Param::class)) {
                        $optional = $paraminfo->optional;
                        $realparams[] = rtrim(Core::toLog('(' . $type . ')', ' -' . $paraminfo->name . ($optional ? self::getTypeHint($paraminfo->defaults) : '') . ''));
                    }
                }
                $hdata[$methodname]['params'] = $realparams;
            } else {
                $hdata = Core::iterate($handlers, function ($v, $k) {
                    return [$k => $v[0]];
                });
                $hdata = Core::extractKeys($hdata, array_keys($handlers));
            }

            $help = [$help, CliUi::arrayToCli($hdata, 1), $this->missingparam];
            $ctx->response()->setContent(CliUi::arrayToCli($help));
        }

        return $ctx;
    }

    private static function getTypeHint(mixed $data): string
    {
        $out = '';
        $nextType = gettype($data);
        // Cast the variable to the target type if possible
        switch ($nextType) {
            case 'boolean':
                $out = '=' . ($data ? 'true' : 'false');
                break;
            case 'array':
                $out = '=' . self::formatData($data) . '';
                break;
            case 'object':
                //no default for objects
                break;
            case 'NULL':
                break;
            default:
                $out = '="' . $data . '"';
        }
        return $out;
    }

    private static function formatData(mixed $data): string
    {
        $out = Core::toLog($data);
        if (is_array($data)) {
            if (empty($data)) {
                $out = '[]';
            } else {
                $out = "['" . implode("','", $data) . "']";
            }
        }
        return $out;
    }


}
