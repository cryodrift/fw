<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

/**
 * @deprecated
 */
trait DbHelperCreate
{
    /**
     * @param string $name 'c_tables.sql, c_indexes.sql, c_triggers.sql, c_views.sql'
     * @param string $delim 'END;' ';'
     * @return string
     */
    public function create(string $name = 'c_.sql', string $delim = ';'): string
    {
        if (!file_exists($name)) {
            $ref = new \ReflectionClass(get_called_class());
            $path = dirname($ref->getFileName()) . '/' . $name;
        } else {
            $path = $name;
        }
        return $this->runQueriesFromFile($path, $delim);
    }
}
