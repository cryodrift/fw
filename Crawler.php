<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * $cfg[\cryodrift\fw\Crawler::class] = [
 * 'sites' => [
 * 'https://www.webpage.at/folder' => [
 * // format     'css selector'=>'column_name',
 * '.someclassname' => [
 * 'href' => 'url',
 * '#textcontent' => 'title'
 * ],
 * '.someotherclassname' => [
 * '#textcontent' => 'content'
 * ],
 * ]
 * ]
 * ];
 * all selectors must find the same amount of nodes
 * the site must be part of the url
 */
abstract class Crawler
{
    public function __construct(private array $sites)
    {
    }


    public function extract(string $url, string $html): array
    {
        $doc = $this->dom($html);
        $out = [];
        if ($doc) {
            $parts = explode('/', $url);
            while (count($parts)) {
                $urlpart = implode('/', $parts);
                if (Core::getValue($urlpart, $this->sites)) {
                    foreach ($this->sites[$urlpart] as $selector => $column) {
                        // create tmp array for each selector; in final $out we merge all selector arrays by index
                        $seltmp = [];
                        $data = $this->select($doc, $selector);
                        Core::echo(__METHOD__, $this->cssToXpath($selector), $data);
                        foreach ($data as $elem) {
                            $tmp = [];
                            foreach ($column as $attr => $col) {
                                if (isset($elem[$attr])) {
                                    $tmp[$col] = $elem[$attr];
                                }
                            }
                            if ($tmp !== []) {
                                $seltmp[] = $tmp;
                            }
                        }
                        // merge selector tmp into final output by index
                        foreach ($seltmp as $i => $row) {
                            if (!isset($out[$i])) {
                                $out[$i] = [];
                            }
                            foreach ($row as $k => $v) {
                                $out[$i][$k] = $v;
                            }
                        }
                    }
                }
                array_pop($parts);
            }
        }
        return $out;
    }

    /**
     * Select first text (or attribute) for selector; returns null when not found.
     * Supports:
     * - XPath when selector starts with "//"
     * - Basic CSS: tag, #id, .class, [attr], [attr=value], descendant combinator (space)
     * - Optional attribute extraction suffix: selector@attr
     */
    private function select(DOMXPath $xp, string $selector): array
    {
        if (str_contains($selector, '@') && !str_starts_with($selector, '//')) {
            // only treat suffix after last @ as attribute when it's not an XPath
            $pos = strrpos($selector, '@');
            if ($pos !== false) {
                $selector = substr($selector, 0, $pos);
            }
        }
        $xpath = str_starts_with($selector, '//') ? $selector : $this->cssToXpath($selector);
        if ($xpath === '') {
            return [];
        }
        $nodelist = $xp->query($xpath);
        if (!$nodelist || $nodelist->length === 0) {
            return [];
        }

        // Loop over all matching nodes and collect data
        $results = [];
        foreach ($nodelist as $n) {
            if ($n instanceof DOMElement) {
                $row = $this->collectNodeData($n);
                if ($row !== []) {
                    $results[] = $row;
                }
            }
        }

        return $results;
    }

    private function collectNodeData(DOMElement $n): array
    {
        $out = [];
        if ($n->hasAttributes()) {
            foreach ($n->attributes as $a) {
                $out[$a->name] = $a->value;
            }
        }
        // add text content for non-void elements when not empty
        $void = [
          'area',
          'base',
          'br',
          'col',
          'embed',
          'hr',
          'img',
          'input',
          'link',
          'meta',
          'param',
          'source',
          'track',
          'wbr'
        ];
        $name = strtolower($n->tagName);
        $text = trim($n->textContent);
        if ($text !== '' && !in_array($name, $void, true)) {
            $out['#textcontent'] = $text;
        }
        return $out;
    }

    private function cssToXpath(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }
        $parts = preg_split('~\s+~', $css) ?: [];
        $x = [];
        foreach ($parts as $part) {
            $x[] = $this->cssSimplePartToXpath($part);
        }
        return '//' . implode('//', array_filter($x));
    }

    private function cssSimplePartToXpath(string $part): string
    {
        $tag = '*';
        $predicates = [];
        // id
        if (preg_match('~#([A-Za-z0-9_\-:]+)~', $part, $m)) {
            $predicates[] = "@id='{$m[1]}'";
            $part = str_replace($m[0], '', $part);
        }
        // class (single)
        if (preg_match('~\.([A-Za-z0-9_\-:]+)~', $part, $m)) {
            $predicates[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$m[1]} ')";
            $part = str_replace($m[0], '', $part);
        }
        // attribute [attr=value] or [attr]
        if (preg_match_all('~\[([^\]=]+)(?:=\"?([^\]"]+)\"?)?\]~', $part, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (isset($m[2]) && $m[2] !== '') {
                    $predicates[] = "@{$m[1]}='{$m[2]}'";
                } else {
                    $predicates[] = "@{$m[1]}";
                }
                $part = str_replace($m[0], '', $part);
            }
        }
        // remaining letters are tag
        $remaining = trim($part);
        if ($remaining !== '') {
            $tag = $remaining;
        }
        if ($predicates) {
            return $tag . '[' . implode(' and ', $predicates) . ']';
        }
        return $tag;
    }

    private function dom(string $html): ?DOMXPath
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $ok = $doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? new DOMXPath($doc) : null;
    }


}
