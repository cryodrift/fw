<?php

namespace cryodrift\fw\trait;

use cryodrift\fw\cli\Colors;
use cryodrift\fw\Core;
use PDO;
use Exception;

/**
 * Dieses Trait ermöglicht die Migration von SQLite-Tabellen basierend auf einer SQL-Datei.
 */
trait DbHelperMigrate
{
    private array $runafter = [];

    public function migrate(string $name = 'c_tables.sql', $delim = ';'): string
    {
        if (!file_exists($name)) {
            $ref = new \ReflectionClass(get_called_class());
            $path = dirname($ref->getFileName()) . '/' . $name;
        } else {
            $path = $name;
        }
        $queries = self::extractQueriesFromFile($path, $delim);
        return $this->migrateSql(iterator_to_array($queries));
    }

    private function dropTriggers(): array
    {
        $result = [];
        foreach ($this->getAllDbNamesExceptBackup() as $dbName) {
            $triggers = $this->query("select sql from " . $dbName . ".sqlite_master where type = 'trigger'", []);
            $result[$dbName] = $triggers;
            foreach ($triggers as $row) {
                $firstLine = strtolower(Core::getValue(0, explode("\n", Core::getValue('sql', $row), 2)));
                $sql = $this->buildDropTriggerSql($firstLine, $dbName);
                if ($sql) {
                    $this->query($sql, []);
                }
            }
        }
        return $result;
    }

    private function createTriggers(array $triggers): void
    {
        // If we received a flat list (backward compatibility), treat it as 'main'
        $isAssoc = array_keys($triggers) !== range(0, count($triggers) - 1);
        if (!$isAssoc) {
            $triggers = ['main' => $triggers];
        }
        foreach ($triggers as $dbName => $rows) {
            foreach ($rows as $row) {
                $sql = Core::getValue('sql', $row);
                if ($sql) {
                    $sql = $this->normalizeCreateTriggerSql($sql, $dbName);
                    $this->query($sql, []);
                }
            }
        }
    }

    private function getAllDbNamesExceptBackup(): array
    {
        $rows = $this->query('PRAGMA database_list', []);
        $names = [];
        foreach ($rows as $row) {
            $name = Core::getValue('name', $row);
            if ($name === 'backup') {
                continue;
            }
            // collect all (main, temp, and attached) except 'backup'
            $names[] = $name;
        }
        return $names;
    }

    private function buildDropTriggerSql(string $firstLine, string $dbName): ?string
    {
        // Extract optional schema and trigger name
        if (preg_match('/^\s*(?:create|drop)\s+trigger\s+(?:if\s+not\s+exists\s+|if\s+exists\s+)?(?:(\w+)\.)?(\w+)/i', $firstLine, $m)) {
            $schema = Core::getValue(1, $m);
            $name = Core::getValue(2, $m);
            $qualified = $schema ? ($schema . '.' . $name) : (($dbName !== 'main') ? ($dbName . '.' . $name) : $name);
            return 'DROP TRIGGER IF EXISTS ' . $qualified;
        }
        return null;
    }

    private function normalizeCreateTriggerSql(string $sql, string $dbName): string
    {
        // Ensure IF NOT EXISTS and proper schema qualification for non-main DBs
        if (preg_match('/^\s*CREATE\s+TRIGGER\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:(\w+)\.)?(\w+)\s*(.*)$/is', $sql, $m)) {
            $schema = Core::getValue(1, $m);
            $name = Core::getValue(2, $m);
            $rest = Core::getValue(3, $m);
            $qualified = $schema ? ($schema . '.' . $name) : (($dbName !== 'main') ? ($dbName . '.' . $name) : $name);
            return 'CREATE TRIGGER IF NOT EXISTS ' . $qualified . ' ' . $rest;
        }
        // Fallback: just add IF NOT EXISTS
        return str_replace('CREATE TRIGGER', 'CREATE TRIGGER IF NOT EXISTS', $sql);
    }

    protected function migrateSql(array $queries): string
    {
        $runafterafter = [];
        $out = '';
        $triggers = $this->dropTriggers();
        foreach ($queries as $query) {
            try {
                [$tableName, $tableBody] = self::getTableParts($query);
                if ($tableName) {
                    $createSQL = 'CREATE TABLE IF NOT EXISTS ' . $tableName . ' (' . $tableBody . ');';
                    $out .= $previous = $this->processTable($tableName, $createSQL);
                    Core::echo(__METHOD__, Colors::get('table processed', Colors::FG_light_green), Colors::get($tableName, Colors::FG_light_cyan));
                } else {
                    $this->getPdo()->exec($query);
                }
            } catch (Exception $ex) {
                Core::echo(__METHOD__, Colors::get('[ERROR]', Colors::FG_light_red), $this->getPdo()->errorInfo(), $ex, $query);
                $runafterafter[] = $query;
            }
        }
        foreach ($this->runafter as $key => $query) {
            try {
                if (str_starts_with($query, 'TXN:')) {
                    $sqls = trim(substr($query, 4));
                    $stmts = array_filter(array_map('trim', explode(';', $sqls)));
                    $this->getPdo()->beginTransaction();
                    foreach ($stmts as $stmt) {
                        $this->getPdo()->exec($stmt);
                    }
                    $this->getPdo()->commit();
                } else {
                    $this->getPdo()->exec($query);
                }
                Core::echoTmp(__METHOD__, Colors::get('[runafter OK]', Colors::FG_light_green));
            } catch (Exception $ex) {
                try {
                    if ($this->getPdo()->inTransaction()) {
                        $this->getPdo()->rollBack();
                    }
                } catch (Exception $e2) {
                }
                Core::echo(__METHOD__, Colors::get('[runafter ERROR]', Colors::FG_light_red), $this->getPdo()->errorInfo(), $ex, $query);
            }
            unset($this->runafter[$key]);
        }
        foreach ($runafterafter as $query) {
            try {
                $this->getPdo()->exec($query);
                Core::echo(__METHOD__, Colors::get('[runafterafter OK]', Colors::FG_light_green));
            } catch (Exception $ex) {
                Core::echo(__METHOD__, Colors::get('[runafterafter ERROR]', Colors::FG_light_red), $this->getPdo()->errorInfo(), $ex, $query);
            }
        }
        $this->createTriggers($triggers);

        return $out;
    }

    private function processTable(string $table, string $createSQL): string
    {
        $newTableName = $table . '_new';
        $out = '';
        [$dbname, $tablename, $backupname] = $this->splitTablename($table);
        Core::echo(__METHOD__, Colors::get('[START Process Table]', Colors::FG_light_blue), Colors::get($tablename, Colors::FG_light_cyan));
        // Erstinstallation/leerem DB-Fall: Tabelle direkt erstellen oder vorhandene _new umbenennen
        if (!$this->tableExists($table)) {
            // Falls von einem früheren Lauf eine _new-Tabelle existiert, zuerst versuchen umzubenennen
            if ($this->tableExists($newTableName)) {
                try {
                    $this->getPdo()->exec("ALTER TABLE $newTableName RENAME TO $tablename");
                    $out .= Core::toLog(__METHOD__, "$table: Vorherige _new-Tabelle umbenannt (Erstinstallation/Recovery).", null, null);
                    return $out;
                } catch (Exception $ex) {
                    Core::echo(__METHOD__, Colors::get('[ERROR]', Colors::FG_light_red), $ex->getMessage());
                    // Wenn das Umbenennen fehlschlägt, alte _new-Tabelle entfernen und frisch erstellen
                    $this->getPdo()->exec("DROP TABLE IF EXISTS $newTableName");
                }
            }
            // Tabelle frisch erstellen (keine Migration erforderlich)
            Core::echo(__METHOD__, Colors::get('[INIT create new]', Colors::FG_light_green), $newTableName);
            $this->getPdo()->exec($createSQL);
            $out .= Core::toLog(__METHOD__, "$table: Tabelle neu erstellt (keine vorherige Version vorhanden).", null, null);
            return $out;
        }

        // Normale Migrationslogik für bestehende Tabellen
        $currentColumns = $this->getCurrentSchema($table);
        $currentConstraints = $this->getTableConstraints($table);

        // Stelle sicher, dass die _new-Tabelle frisch ist und exakt dem Ziel-Schema entspricht
        try {
            $this->getPdo()->exec("DROP TABLE IF EXISTS $newTableName");
            Core::echo(__METHOD__, Colors::get('[DONE]', Colors::FG_light_green), 'DROP ' . $newTableName);

            // Erzeuge _new ohne IF NOT EXISTS, damit wirklich das gewünschte Schema angelegt wird
            $createNewSql = str_replace("CREATE TABLE IF NOT EXISTS $table", "CREATE TABLE $newTableName", $createSQL);

            $this->getPdo()->exec($createNewSql);
            Core::echo(__METHOD__, Colors::get('[DONE create temp table]', Colors::FG_light_green), $newTableName);
        } catch (Exception $ex) {
            // Wenn das Anlegen der _new Tabelle fehlschlägt, Migration für diese Tabelle abbrechen
            Core::toLog(__METHOD__, "$table: Fehler beim Anlegen der _new-Tabelle. Migration übersprungen.", null, $ex->getMessage());
            return $out;
        }

        $newColumns = $this->getCurrentSchema($newTableName);
        $newConstraints = $this->getTableConstraints($newTableName);

        $schemaChanged = ($currentColumns !== $newColumns) || ($currentConstraints !== $newConstraints);
        if ($schemaChanged) {
            Core::echo(__METHOD__, Colors::get('[INFO]', Colors::FG_light_cyan), 'schema changed');
            $out .= Core::toLog(__METHOD__, "$table: Schema/Constraints geändert. Migration läuft...", ['columns' => $currentColumns, 'constraints' => $currentConstraints], ['columns' => $newColumns, 'constraints' => $newConstraints]);
            // Build INSERT column list and SELECT expressions ensuring:
            // - Renamed Spalten werden aus alten Spalten befüllt ("--renamed oldname")
            // - NOT NULL neue Spalten ohne Default erhalten sichere Fallback-Werte
            $insertCols = [];
            $selectExprs = [];
            $renameMap = $this->extractRenameMap($createSQL);
            // Iterate in new table column order to keep things stable
            foreach (array_keys($newColumns) as $colName) {
                if (array_key_exists($colName, $currentColumns)) {
                    $insertCols[] = $colName;
                    $selectExprs[] = $colName;
                } else {
                    // Prüfe ob diese Spalte umbenannt wurde und aus alter Spalte befüllt werden kann
                    $old = $renameMap[strtolower($colName)] ?? null;
                    if ($old && array_key_exists($old, array_change_key_case($currentColumns, CASE_LOWER))) {
                        $insertCols[] = $colName;
                        $selectExprs[] = $old;
                    } else {
                        // Column does not exist in old table; if it's NOT NULL, provide a safe fallback literal
                        $colMeta = $newColumns[$colName] ?? [];
                        if (!empty($colMeta['notnull'])) {
                            $insertCols[] = $colName;
                            $selectExprs[] = $this->getFallbackLiteralForType($colMeta['type'] ?? '');
                        }
                        // If nullable, omit it so default/NULL applies implicitly
                    }
                }
            }
            if (!empty($insertCols)) {
                $columnsList = implode(', ', $insertCols);
                $selectList = implode(', ', $selectExprs);
                if ($this->tableExists($table)) {
                    Core::echo(__METHOD__, Colors::get('[INFO]', Colors::FG_light_cyan), 'insert columns in tmp table');
                    $this->getPdo()->exec("INSERT INTO $newTableName ($columnsList) SELECT $selectList FROM $table");
                }
            }
            if ($this->tableExists($table)) {
                Core::echo(__METHOD__, Colors::get('[INFO]', Colors::FG_light_cyan), 'table exists', $table);
                // Restore fehlender Spalten
                $this->restoreMissingColumns($table, $currentColumns, $newColumns);
                Core::echo(__METHOD__, Colors::get('[INFO]', Colors::FG_light_cyan), 'columns restored', $table);
                // Backup vor Löschen erstellen
                $this->backupTableToFile($table);
                Core::echo(__METHOD__, Colors::get('[INFO]', Colors::FG_light_cyan), 'backup created', $table);
            }

            // Führe DROP und RENAME atomar innerhalb einer Transaktion aus, um inkonsistente Zustände zu vermeiden
            $this->runafter[] = "TXN: DROP TABLE IF EXISTS $table; ALTER TABLE $newTableName RENAME TO $tablename";

            $out .= Core::toLog(__METHOD__, "$table: Migration abgeschlossen.", null, null);
        } else {
            $this->runafter[] = "DROP TABLE IF EXISTS $newTableName";
            $out .= Core::toLog(__METHOD__, "$table: Keine Änderungen am Schema/Constraints.", null, null);
        }
        return $out;
    }

    private function restoreMissingColumns(string $table, array $currentColumns, array $newColumns): void
    {
        $restoredColumns = array_diff(array_keys($newColumns), array_keys($currentColumns));
        if (empty($restoredColumns)) {
            return;
        }
        [$dbname, $tablename, $backupname] = $this->splitTablename($table);
        $backupFile = preg_replace(['/^sqlite:/', '/\.sqlite$/'], '', $this->connectionstring) . '_migrations.sqlite';
        try {
            $this->getPdo()->exec("ATTACH DATABASE '$backupFile' AS backupdb");
        } catch (Exception $ex) {
        }


        $stmt = $this->getPdo()->query("SELECT name FROM backupdb.sqlite_master WHERE type='table' AND name LIKE '{$backupname}_backup_%' ORDER BY name DESC");
        $backups = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($backups as $backupTable) {
            $availableColumnsStmt = $this->getPdo()->query("PRAGMA backupdb.table_info($backupTable)");
            $availableColumns = [];
            while ($col = $availableColumnsStmt->fetch(PDO::FETCH_ASSOC)) {
                $availableColumns[] = $col['name'];
            }

            $columnsToRestore = array_intersect($restoredColumns, $availableColumns);
            if (!empty($columnsToRestore)) {
                foreach ($columnsToRestore as $col) {
                    if ($col !== 'id') {
                        $this->getPdo()->exec("UPDATE {$table}_new SET $col = (SELECT $col FROM backupdb.$backupTable WHERE {$table}_new.id = backupdb.$backupTable.id)");
                    }
                }
                Core::toLog(__METHOD__, "Wiederhergestellte Spalten", $columnsToRestore, $backupTable);
                break;
            }
        }

        $this->getPdo()->exec("DETACH DATABASE backupdb");
    }

    private function backupTableToFile(string $table): void
    {
        [$dbname, $tablename, $backupname] = $this->splitTablename($table);
        $backupFile = preg_replace(['/^sqlite:/', '/\.sqlite$/'], '', $this->connectionstring) . '_migrations.sqlite';
        $backupTableName = $backupname . $tablename . '_backup_' . date('Ymd_His');

        try {
            $this->getPdo()->exec("ATTACH DATABASE '$backupFile' AS backupdb");
        } catch (Exception $ex) {
//            Core::echo(__METHOD__,$ex);
        }

        $this->getPdo()->exec("CREATE TABLE IF NOT EXISTS backupdb.$backupTableName AS SELECT * FROM $table");
        $this->getPdo()->exec("DETACH DATABASE backupdb");

        Core::toLog(__METHOD__, "Backup erstellt", $backupFile, $backupTableName);
    }

    private function getCurrentSchema(string $table): array
    {
        $columns = [];
        [$dbname, $tablename] = $this->splitTablename($table);
        if ($this->tableExists($table)) {
            $stmt = $this->getPdo()->query("PRAGMA " . $dbname . "table_info($tablename)");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Snapshot includes declared type and NOT NULL flag to detect nullability changes
                $columns[$row['name']] = [
                  'type' => strtolower($row['type']),
                  'notnull' => (int)$row['notnull'],
                    // include default value expression for change detection
                  'default' => $row['dflt_value'],
                ];
            }
        }
        return $columns;
    }

    private function getTableConstraints(string $table): array
    {
        $constraints = [
          'pk' => [],
          'unique' => [],
        ];
        if (!$this->tableExists($table)) {
            return $constraints;
        }
        [$dbname, $tablename] = $this->splitTablename($table);
        // Primary key columns (ordered by pk sequence)
        $pk = [];
        $stmt = $this->getPdo()->query("PRAGMA " . $dbname . "table_info($tablename)");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['pk'])) {
                $pk[(int)$row['pk']] = $row['name'];
            }
        }
        if (!empty($pk)) {
            ksort($pk);
            $constraints['pk'] = array_values($pk);
        }

        // Unique constraints via unique indexes
        $idxList = $this->getPdo()->query("PRAGMA " . $dbname . "index_list($tablename)");
        while ($idx = $idxList->fetch(PDO::FETCH_ASSOC)) {
            // In SQLite, 'unique' field is 1 for unique indexes
            if (!empty($idx['unique'])) {
                $idxName = $idx['name'];
                $cols = [];
                $idxInfo = $this->getPdo()->query("PRAGMA " . $dbname . "index_info('" . str_replace("'", "''", $idxName) . "')");
                while ($c = $idxInfo->fetch(PDO::FETCH_ASSOC)) {
                    $cols[(int)$c['seqno']] = $c['name'];
                }
                if (!empty($cols)) {
                    ksort($cols);
                    $constraints['unique'][] = array_values($cols);
                }
            }
        }

        // Normalize unique sets by sorting the list of constraints for stable comparison
        if (!empty($constraints['unique'])) {
            // Sort each unique set lexicographically joined by comma
            usort($constraints['unique'], function ($a, $b) {
                return strcmp(implode(',', $a), implode(',', $b));
            });
        }

        return $constraints;
    }

    private function tableExists(string $table): bool
    {
        [$dbname, $tablename] = $this->splitTablename($table);
        if ($dbname) {
            $stmt = $this->getPdo()->prepare("SELECT name FROM " . $dbname . "sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$tablename]);
        } else {
            $stmt = $this->getPdo()->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$table]);
        }

        return (bool)$stmt->fetchColumn();
    }

    // Erwartet, dass die Klasse diese Methoden bereitstellt
    abstract protected function getPdo(): PDO;

    abstract public static function extractQueriesFromFile(string $pathname, string $delim = ';'): iterable;

    private function getFallbackLiteralForType(string $declType): string
    {
        $t = strtolower(trim($declType));
        // Numeric-like types: int, real, float, double, numeric
        if ($t === '' || str_contains($t, 'int') || str_contains($t, 'real') || str_contains($t, 'floa') || str_contains($t, 'doub') || str_contains($t, 'num')) {
            return '0';
        }
        // Blob types
        if (str_contains($t, 'blob')) {
            return "X''"; // empty blob literal
        }
        // Default: text-like
        return "''";
    }

    /**
     * Extrahiert Umbenennungen aus dem CREATE TABLE SQL.
     * Erwartetes Format innerhalb der Spaltendefinition:
     *   newcolumnname ... , --renamed oldcolumnname
     * Gibt ein Array im Format [ strtolower(new) => strtolower(old) ] zurück.
     */
    private function extractRenameMap(string $createSQL): array
    {
        $map = [];
        // Inhalt innerhalb der Klammern der CREATE TABLE Definition extrahieren
        $openPos = strpos($createSQL, '(');
        $closePos = strrpos($createSQL, ')');
        if ($openPos === false || $closePos === false || $closePos <= $openPos) {
            return $map;
        }
        $inside = substr($createSQL, $openPos + 1, $closePos - $openPos - 1);
        // Zeilenweise durchgehen
        $lines = preg_split('/\r?\n/', $inside);
        foreach ($lines as $line) {
            $ln = trim($line);
            if ($ln === '') {
                continue;
            }
            // Nur Zeilen mit dem Hinweis berücksichtigen
            if (stripos($ln, '--') === false || stripos($ln, 'renamed') === false) {
                continue;
            }
            // Nachkommata am Ende entfernen
            $ln = rtrim($ln, ',');
            // Regex: Spaltenname am Anfang, irgendwo dahinter Kommentar mit --renamed ALTNAME
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\b.*?--\s*renamed\s+([A-Za-z_][A-Za-z0-9_]*)/i', $ln, $m)) {
                $new = strtolower($m[1]);
                $old = strtolower($m[2]);
                if ($new !== '' && $old !== '') {
                    $map[$new] = $old;
                }
            }
        }
        return $map;
    }
}
