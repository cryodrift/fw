<?php

namespace cryodrift\fw\trait;

use cryodrift\fw\Core;

/**
 * Purpose: transform tables/views SQL into arrays like [name => [column => definition, ...]].
 */
trait DbHelperSchema
{
    /**
     * Return simplified schema mapping for tables: table => [column => sqlType,...]
     */
    public function schemaTableFromSql(string $sql): array
    {
        $tables = $this->schemaParseSqlTables($sql);
        return $this->flattenNameToColumns($tables);
    }

    /**
     * Return simplified schema mapping for views: view => [column => inferredSqlType,...]
     */
    public function schemaViewFromSql(string $sql): array
    {
        $views = $this->schemaParseSqlViews($sql);
        return $this->flattenNameToColumns($views);
    }

    /**
     * Parse a blob of SQL that may contain both CREATE TABLE and CREATE VIEW and
     * return a merged name => [column => definition] mapping.
     */
    public function schemaFromSql(string $sql): array
    {
        $tables = $this->flattenNameToColumns($this->schemaParseSqlTables($sql));
        $views = $this->flattenNameToColumns($this->schemaParseSqlViews($sql));
        return $tables + $views; // tables first, views may override if same name
    }

    /**
     * Parse SQL file content to extract table definitions.
     * @return array name => ['columns'=>[col=>type,...], 'hasTimestamps'=>bool]
     */
    protected function schemaParseSqlTables(string $sql): array
    {
        $tables = [];
        foreach (self::extractQueries($sql) as $query) {
            [$name, $body] = self::getTableParts($query);
            if ($name) {
                $columnsDefinition = trim($body, '();');

                // Parse columns
                $columns = [];
                //TODO replace this with a good explode
                $columnPattern = '/\s*(\w+)\s+([\w()]+)(?:\s+(?:PRIMARY\s+KEY|UNIQUE|NOT\s+NULL|DEFAULT\s+[^,]+))*(?:,|$)/i';

                if (preg_match_all($columnPattern, $columnsDefinition, $columnMatches, PREG_SET_ORDER)) {
                    foreach ($columnMatches as $columnMatch) {
                        $columnName = strtolower($columnMatch[1]);
                        $columnType = $columnMatch[2];

                        // Skip FOREIGN KEY constraints
                        if ($columnName !== 'foreign' && $columnName !== 'unique') {
                            $columns[$columnName] = $columnType;
                        }
                    }
                }

                $tables[strtolower($name)] = [
                  'columns' => $columns,
                  'hasTimestamps' => isset($columns['created']) && isset($columns['changed'])
                ];
            }
        }

        return $tables;
    }

    /**
     * Parse SQL file content to extract view definitions.
     * @return array name => ['columns'=>[col=>type,...], 'hasTimestamps'=>false]
     */
    protected function schemaParseSqlViews(string $sql): array
    {
        $views = [];
        $pattern = '/CREATE\s+VIEW\s+(\w+)\s+AS\s+(.*?);(?=\s*(?:--|DROP|CREATE|$))/s';

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $viewName = $match[1];
                $selectStatement = $match[2];

                // Parse columns from SELECT statement
                $columns = $this->schemaParseViewColumns($selectStatement);

                $views[$viewName] = [
                  'columns' => $columns,
                  'hasTimestamps' => false // Views typically don't have timestamps
                ];
            }
        }

        return $views;
    }

    /**
     * Parse columns from a view's SELECT statement.
     * @return array alias => inferredSqlType
     */
    protected function schemaParseViewColumns(string $selectStatement): array
    {
        $columns = [];

        // Pattern to match column aliases in SELECT statement
        // This handles cases like: column AS alias, column_name AS alias_name, etc.
        $pattern = '/(?:^|\s|,)\s*(?:(?:\w+\.)?(\w+)|(?:[^,]+?))\s+AS\s+(\w+)(?=\s*(?:,|\s+FROM|\s*$))/is';

        if (preg_match_all($pattern, $selectStatement, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columnAlias = $match[2];

                // Infer column type based on common naming patterns or default to TEXT
                $columnType = $this->schemaInferColumnType($columnAlias);

                $columns[$columnAlias] = $columnType;
            }
        }

        return $columns;
    }

    /**
     * Infer a best-effort SQLite-friendly type name from a column name.
     */
    private function schemaInferColumnType(string $columnName): string
    {
        // Common naming patterns for different types
        if (preg_match('/^(?:id|.*_id)$/', $columnName)) {
            return 'INTEGER';
        } elseif (preg_match('/^(?:count|total|sum|amount|quantity|price|balance)/', $columnName)) {
            return 'NUMERIC';
        } elseif (preg_match('/^(?:is_|has_)/', $columnName)) {
            return 'BOOLEAN';
        } elseif (preg_match('/^(?:date|time|created|changed|timestamp)/', $columnName)) {
            return 'NUMERIC'; // SQLite stores dates as NUMERIC
        } else {
            return 'TEXT';
        }
    }

    /**
     * Helper: flatten rich structures (with 'columns') to name => columns-only mappings.
     */
    private function flattenNameToColumns(array $nameToInfo): array
    {
        $out = [];
        foreach ($nameToInfo as $name => $info) {
            $out[$name] = isset($info['columns']) && is_array($info['columns']) ? $info['columns'] : [];
        }
        return $out;
    }


    /**
     * Generate model class content
     * @param string $tableName
     * @param string $modelName
     * @param array $tableInfo
     * @param bool $isView
     * @return string
     */
    protected function schemaGenerateModelClass(string $tableName, string $modelName, array $tableInfo, string $namespace, bool $isView = false): string
    {
        $columns = $tableInfo['columns'];
        $hasTimestamps = $tableInfo['hasTimestamps'] ?? false;

        $properties = [];
        $getters = [];
        $setters = [];

        foreach ($columns as $columnName => $columnType) {
            $phpType = $this->sqlTypeToPHP($columnType);
            $propertyName = $columnName;
            $methodName = ucfirst($this->camelCase($columnName));

            // Skip created and changed as they're handled by ModelTimestamps
            if ($hasTimestamps && ($columnName === 'created' || $columnName === 'changed')) {
                continue;
            }

            // Property
            $properties[] = "    /**\n     * " . ucfirst(str_replace('_', ' ', $columnName)) . "\n     * @var $phpType\n     */\n    protected $phpType \$$propertyName = " . $this->getDefaultValue($phpType) . ";";

            // Getter
            $getters[] = "    /**\n     * Get the " . strtolower(str_replace('_', ' ', $columnName)) . "\n     * @return $phpType\n     */\n    public function get$methodName(): $phpType\n    {\n        return \$this->$propertyName;\n    }";

            // Setter
            $setters[] = "    /**\n     * Set the " . strtolower(str_replace('_', ' ', $columnName)) . "\n     * @param $phpType \$$propertyName\n     * @return \$this\n     */\n    public function set$methodName($phpType \$$propertyName): self\n    {\n        \$this->$propertyName = \$$propertyName;\n        return \$this;\n    }";
        }

        $useTraits = [];
        if ($hasTimestamps) {
            $useTraits[] = "use \\cryodrift\\fw\\trait\\ModelTimestamps;";
        } else {
            $useTraits[] = "use \\cryodrift\\fw\\trait\\ModelColumns;";
        }

        $entityType = $isView ? "view" : "table";
        $content = "<?php\n\nnamespace $namespace;\n\n" . implode("\n", $useTraits) . "\n\n/**\n * Model class for the $tableName $entityType\n */\nclass " . ucfirst($modelName) . "\n{\n";

        if (!empty($useTraits)) {
            $content .= "    " . str_replace("\\cryodrift\\fw\\trait\\", "", implode("\n    ", $useTraits)) . "\n\n";
        }

        $content .= implode("\n\n", $properties) . "\n\n";
        $content .= implode("\n\n", $getters) . "\n\n";
        $content .= implode("\n\n", $setters) . "\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Convert SQL type to PHP type
     * @param string $sqlType
     * @return string
     */
    private function sqlTypeToPHP(string $sqlType): string
    {
        $sqlType = strtolower($sqlType);

        if (strpos($sqlType, 'int') !== false) {
            return '?int';
        } elseif (strpos($sqlType, 'text') !== false || strpos($sqlType, 'char') !== false) {
            return '?string';
        } elseif (strpos($sqlType, 'numeric') !== false || strpos($sqlType, 'real') !== false || strpos($sqlType, 'float') !== false) {
            return '?float';
        } elseif (strpos($sqlType, 'bool') !== false) {
            return '?bool';
        } else {
            return '?string';
        }
    }

    /**
     * Get default value for PHP type
     * @param string $phpType
     * @return string
     */
    private function getDefaultValue(string $phpType): string
    {
        if ($phpType === '?int' || $phpType === '?float' || $phpType === '?string' || $phpType === '?bool') {
            return 'null';
        } elseif ($phpType === 'int') {
            return '0';
        } elseif ($phpType === 'float') {
            return '0.0';
        } elseif ($phpType === 'string') {
            return "''";
        } elseif ($phpType === 'bool') {
            return 'false';
        } else {
            return 'null';
        }
    }

    /**
     * Convert snake_case to camelCase
     * @param string $string
     * @return string
     */
    private function camelCase(string $string): string
    {
        $result = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
        return $result;
    }

}
