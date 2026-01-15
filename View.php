<?php

namespace cryodrift\fw;


class View
{
    private bool $valid = false;
    private array $vars = [];
    private array $translations = [];
    private string $content = '';
    private string $contentfile = '';


    public function __toString(): string
    {
        $this->beforeRender();
        if ($this->hasContent()) {
            if (!$this->content && $this->contentfile) {
                $this->content = Core::fileReadOnce($this->contentfile);
            }

            $out = $this->renderIncludes($this->content);
            $out = $this->render($out, $this->translations);
            $out = $this->render($out, $this->vars);
            $out = $this->render($out, $this->translations);
            return $out;
        }
        return '';
    }

    protected function beforeRender(): void
    {
    }

    public function hasContent(): bool
    {
        return $this->valid;
    }

    public function setContent(string $content): self
    {
        $this->valid = true;
        $this->content = $content;
        return $this;
    }

    public function addVars(array $vars, bool $secure = true, array $nonsecureitems = []): self
    {
        $this->valid = true;

        if ($secure) {
            $vars = $this->secureVarsForHtml($vars, $nonsecureitems);
        }

        $this->vars = array_merge($this->vars, $vars);
        return $this;
    }


    public function setVars(array $vars, bool $secure = true): self
    {
        if ($secure) {
            $vars = $this->secureVarsForHtml($vars);
        }
        $this->vars = $vars;
        return $this;
    }

    public function &getVars(): array
    {
        return $this->vars;
    }


    public function setContentFile(string $pathname): self
    {
        $this->valid = true;
        $this->contentfile = $pathname;
        return $this;
    }


    private function renderIncludes(string $content, string $name = 'file', ?callable $fnk = null): string
    {
        if ($fnk === null) {
            $fnk = fn(string $fn) => file_get_contents($fn);
        }
        $delim = '{{@' . $name . '@}}';
        $parts = explode($delim, $content);

        // find filenames
        foreach ($parts as $key => $part) {
            if ($key % 2 === 1) {
                $found = $fnk($part);
                $found = $this->renderIncludes($found, $name, $fnk);
                $content = str_replace($delim . $part . $delim, $found, $content);
            }
        }

        return $content;
    }

    /**
     * handle blocks {{@}}key{{@}}content{{@}}key{{@}}
     * @param string $name
     * @param string $template
     * @param array $data
     * @return string
     */
    private function renderBlock(string $name, string $template, array $data): string
    {
        $blockname = '{{@}}' . $name . '{{@}}';
        $parts = explode($blockname, $template, 3);
        $content = Core::value(1, $parts);
        if ($content) {
            $block = $blockname;
            $block .= $content;
            $block .= $blockname;
            $out = '';
            foreach ($data as $key => $value) {
                if (is_numeric($key)) {
                    if ($value) {
                        if ($value instanceof View) {
                            Core::echo(__METHOD__, 'ITS a num VIEW', $name);
                            $out .= str_replace('{{@@}}', (string)$value, $content);
                        } elseif (is_array($value)) {
                            Core::echo(__METHOD__, 'ITS a num ARRAY', $name);
                            $out .= $this->render($content, $value);
                        } else {
                            Core::echo(__METHOD__, 'ITS a num STRING', $name);
                            $out .= $this->render($content, ['@@' => $value]);
                        }
                    } else {
                        Core::echo(__METHOD__, 'ITS a NO VALUE', $name,'key:',$key, 'value:',$value);
                    }
                } elseif (is_array($value)) {
                    Core::echo(__METHOD__, 'ITS a ARRAY', $name);
                    $out .= $this->renderBlock($key, $content, $value);
                } else {
                    Core::echo(__METHOD__, 'ITS a SIMPLE', $name);
                    $out .= $this->render($content, ['key' => $key, 'value' => $value]);
                }
            }
            Core::echo(__METHOD__, 'ITS DONE', $name,$block,$out);
            return str_replace($block, $out, $template);
        } else {
            return $template;
        }
    }

    private function render(string $content, array $data): string
    {
        $s = $r = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $content = $this->renderBlock($key, $content, $value);
            }
        }

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                if (is_numeric($key)) {
                    $content .= $value;
                } else {
                    $s[] = '{{' . $key . '}}';
                    $r[] = (string)$value;
                }
            }
        }

        foreach ($data as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $s[] = '{{' . $key . '}}';
                $r[] = $value;
            }
        }

        $content = str_replace($s, $r, $content);
//        echo Core::toLog(__METHOD__, $s, $r,$content,$data);
        $content = str_replace($s, $r, $content);

        return $content;
    }

    /**
     * Recursively secures variables for HTML output
     *
     * @param array $vars The variables to secure
     * @param array $nonsecureitems Keys that should not be secured
     * @return array The secured variables
     */
    protected function secureVarsForHtml(array $vars, array $nonsecureitems = []): array
    {
        $result = [];

        foreach ($vars as $key => $value) {
            // Skip items that should not be secured
            if (in_array($key, $nonsecureitems, true)) {
                $result[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                // Recursively secure nested arrays
                $result[$key] = $this->secureVarsForHtml($value, $nonsecureitems);
            } elseif (is_string($value)) {
                // Secure string values
                $result[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                // Non-string values (like numbers, booleans, etc.) don't need escaping
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function setTranslations(array $translations): void
    {
        $this->translations = $translations;
    }

    public function __debugInfo(): ?array
    {
        $this->beforeRender();
        return get_mangled_object_vars($this);
    }

}
