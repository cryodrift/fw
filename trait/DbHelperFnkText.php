<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

trait DbHelperFnkText
{

    public static function createShortContent($content): string|null
    {
        $maxLength = 100; // Define the maximum length of short content
        // Strip HTML tags
        if ($content) {
            $content = strip_tags($content);
            // Truncate to the desired length
            if (strlen($content) > $maxLength) {
                return substr($content, 0, $maxLength) . '...';
            }
        }
        return $content;
    }

}
