<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use DateTime;
use Exception;
use cryodrift\fw\Core;

trait DbHelperFnkDate
{

    public const array DATENAMES = ['', 'today', 'twodays', 'threedays', 'fourdays', 'fivedays', 'week', 'onlylastweek', 'month'];

    public static function isFutureDate(DateTime $date)
    {
        $now = new DateTime();
        return $date > $now;
    }

    public static function compareDateName($date, $namedDate)
    {
        $out = 0;
        try {
            $now = new DateTime();
            $compareDate = new DateTime($date);
            switch ($namedDate) {
                case 'today':
                    $comp = $compareDate->format('Y-m-d') === $now->format('Y-m-d');
                    $out = $comp;
                    break;
                case 'twodays':
                    $now->modify('-1 day');
                    $comp = $compareDate->format('Y-m-d') >= $now->format('Y-m-d');
                    $out = $comp;
                    break;
                case 'threedays':
                    $now->modify('-2 day');
                    $comp = $compareDate->format('Y-m-d') >= $now->format('Y-m-d');
                    $out = $comp;
                    break;
                case 'fourdays':
                    $now->modify('-3 day');
                    $comp = $compareDate->format('Y-m-d') >= $now->format('Y-m-d');
                    $out = $comp;
                    break;
                case 'fivedays':
                    $now->modify('-4 day');
                    $comp = $compareDate->format('Y-m-d') >= $now->format('Y-m-d');
                    $out = $comp;
                    break;
                case 'onlylastweek':
                    $startOfWeek = clone $now;
                    $startOfWeek->modify('monday this week');
                    $endOfWeek = clone $startOfWeek;
                    $endOfWeek->modify('sunday this week');
                    $out = $compareDate >= $startOfWeek && $compareDate <= $endOfWeek;
                    break;
                case 'week':
                    $last = clone $now;
                    $last->modify('-1 week');
                    $out = $compareDate >= $last;
                    break;
                case 'month':
                    $last = clone $now;
                    $last->modify('-1 month');
                    $out = $compareDate >= $last;
                    break;
                default:
                    $out = 0;
            }
        } catch (Exception $ex) {
//            print_r($ex->getMessage());
//            echo PHP_EOL;
        }
        if (!$out) {
            $out = 0;
        }

        return $out;
    }

    // Function to convert date to the desired format
    public static function dateFormat(null|string $ds)
    {
        if ($ds) {
            if (str_contains($ds, ')')) {
                $ds = preg_replace('/\s*\(.*?\)/', '', $ds);
            }
            if (str_contains($ds, ';')) {
                $ds = Core::pop(explode(';', $ds));
            }
            if (str_contains($ds, ' UT')) {
                $ds = str_replace(' UT', '', $ds);
            }
            if (str_contains($ds, ' --')) {
                $ds = str_replace(' --', ' +', $ds);
            }
            $ts = strtotime($ds);
            if ($ts) {
                return date('Y-m-d h:i:s', $ts);
            } else {
//                Core::echo(__METHOD__,$ds);
                $ds = preg_replace('/.*;\s*(\d{1,2}\s\w+\s\d{4}\s\d{2}:\d{2}:\d{2}).*/ms', '$1', $ds);

                $ds = preg_replace('/\s*\(.*?\)/', '', $ds);

                // Remove any trailing timezone names (like "SE Asia Standard Time")
                $ds = preg_replace('/ [A-Z][a-z]+( [A-Z][a-z]+)*$/', '', $ds);

                if (str_contains($ds, ' UT')) {
                    $ds = str_replace(' UT', ' +0000', $ds);
                }

                if (str_contains($ds, '--100')) {
                    $ds = preg_replace('/--100/', '-0000', $ds);
                }
                // Check if the date string can be parsed by DateTime
                $date = DateTime::createFromFormat(DateTime::RFC2822, $ds);

                if (!$date) {
                    // If parsing fails, try another common format
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $ds);
                }
                if (!$date) {
                    // If parsing fails, try another common format
                    $date = DateTime::createFromFormat('d M Y H:i:s', $ds);
                }

                if (!$date || self::isFutureDate($date)) {
                    return '';
                }
                return $date->format('Y-m-d H:i:s');
            }
        } else {
            return '';
        }
    }

}
