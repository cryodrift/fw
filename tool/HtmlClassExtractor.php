<?php
//declare(strict_types=1);

namespace cryodrift\fw\tool;

use DOMDocument;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;

class HtmlClassExtractor implements Handler
{
    public function handle(Context $ctx): Context
    {
        $content = $ctx->response()->getContent();
        if ($content) {
            $extractedClasses = $this->extractClassesFromHTML($content);
            Core::fileWrite('css_classes.txt', Core::toLog(__METHOD__, $extractedClasses), FILE_APPEND);
        }
        return $ctx;
    }


// Function to extract all class attributes from an HTML file
    function extractClassesFromHTML($html)
    {
        // Create a DOMDocument object
        $dom = new DOMDocument();

        // Suppress warnings for invalid HTML
        libxml_use_internal_errors(true);

        // Load HTML into the DOMDocument
        $dom->loadHTML($html);

        // Restore error handling
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        // Initialize an array to store extracted classes
        $classes = [];

        // Find all elements in the DOM
        $elements = $dom->getElementsByTagName('*');

        foreach ($elements as $element) {
            // Check if the element has a 'class' attribute
            if ($element->hasAttribute('class')) {
                // Get the value of the 'class' attribute
                $classValue = $element->getAttribute('class');

                // Split the class attribute by spaces to get individual classes
                $classArray = explode(' ', $classValue);

                // Add each class to the result array (if not already added)
                foreach ($classArray as $class) {
                    if (!in_array($class, $classes)) {
                        $classes[] = $class;
                    }
                }
            }
        }

        // Return the array of unique class names
        return $classes;
    }


}
