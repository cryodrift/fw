<?php

namespace cryodrift\fw\trait;

use cryodrift\fw\Core;
use Exception;
use PDO;

trait DbHelperTrigger
{
    public function triggerSet(string $name = 'active', bool $on = true, string $dbname = ''): void
    {
        $old = $this->skipexisting;
        $this->skipexisting = true;

        $col = 'trigger_' . $name;
        $val = $on ? 1 : 0;
        $this->insertTriggerControl($col, $val, $dbname);

        $this->skipexisting = $old;
    }

    /**
     * @param array $tables comes from DbHelperSchema::schemaParseSqlTables()
     * @return string
     */
    public function triggerCreateVersions(array $tables): string
    {
        // Build DELETE and UPDATE triggers that write per-column diffs into versions(table_name, column_name, old_data, new_data, user_id, operation)
        // Only active when trigger_versions = 1 in trigger_control
        $out = '';

        foreach ($tables as $tablename => $table) {
            $parts = explode('.', $tablename, 2);
            $dbname = '';
            if (count($parts) === 2) {
                $tablename = $parts[1];
                $dbname = $parts[0] . '.';
            }
            $out .= $this->createVersionsTable($dbname);

            if ($tablename === 'trigger_control' || $tablename === 'versions') {
                continue;
            }
            $columns = $table['columns'] ?? [];
            if (!is_array($columns) || empty($columns)) {
                continue;
            }
            // filter out technical columns
            $colnames = array_keys($columns);
            $colnames = array_values(array_filter($colnames, function ($c) {
                return !in_array($c, ['id', 'created', 'changed', 'deleted'], true);
            }));
            if (empty($colnames)) {
                continue;
            }

            // DELETE trigger

            $dropDelete = 'DROP TRIGGER IF EXISTS __trgname__';
            $deleteValues = [];
            foreach ($colnames as $c) {
                $deleteValues[] = "(OLD.id,'__tablename__', '" . $c . "', OLD." . $c . ", NULL, (SELECT file FROM pragma_database_list WHERE name='user' LIMIT 1), 'DELETE')";
            }
            $createDelete = "CREATE TRIGGER IF NOT EXISTS __trgname__\n" .
              "AFTER DELETE\n" .
              "ON __fulltablename__\n" .
              "FOR EACH ROW\n" .
              "WHEN (SELECT trigger_versions FROM trigger_control) = 1\n" .
              "BEGIN\n" .
              "INSERT INTO versions(table_id, table_name, column_name, old_data, new_data, user_id, operation)\n" .
              "VALUES\n" .
              implode(",\n", $deleteValues) . ";\n" .
              "END";

            $srDel = [
              '__trgname__' => $dbname . 'trg_version_' . $tablename . '_delete',
              '__tablename__' => $tablename,
              '__fulltablename__' => $dbname . $tablename,
            ];
            try {
                $sqlr = str_replace(array_keys($srDel), array_values($srDel), $dropDelete);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }
            try {
                $sqlr = str_replace(array_keys($srDel), array_values($srDel), $createDelete);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }

            // UPDATE trigger

            $dropUpdate = 'DROP TRIGGER IF EXISTS __trgname__';
            $updateInserts = [];
            foreach ($colnames as $c) {
                $updateInserts[] =
                  "INSERT INTO versions(table_id, table_name, column_name, old_data, new_data, user_id, operation)\n" .
                  "SELECT OLD.id, '__tablename__', '" . $c . "', OLD." . $c . ", NEW." . $c . ", (SELECT file FROM pragma_database_list WHERE name='user' LIMIT 1), 'UPDATE'\n" .
                  "WHERE NOT (OLD." . $c . " IS NEW." . $c . ")";
            }
            $createUpdate = "CREATE TRIGGER IF NOT EXISTS __trgname__\n" .
              "AFTER UPDATE\n" .
              "ON __fulltablename__\n" .
              "FOR EACH ROW\n" .
              "WHEN (SELECT trigger_versions FROM trigger_control) = 1\n" .
              "BEGIN\n" .
              implode(";\n", $updateInserts) . ";\n" .
              "END";

            $srUpd = [
              '__trgname__' => $dbname . 'trg_version_' . $tablename . '_update',
              '__fulltablename__' => $dbname . $tablename,
              '__tablename__' => $tablename,
            ];
            try {
                $sqlr = str_replace(array_keys($srUpd), array_values($srUpd), $dropUpdate);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }
            try {
                $sqlr = str_replace(array_keys($srUpd), array_values($srUpd), $createUpdate);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }
        }
        return $out;
    }

    private function createUpdate(array $tablenames): string
    {
        $drop = 'DROP TRIGGER IF EXISTS set__name__';
        $create = '
CREATE TRIGGER IF NOT EXISTS set__name__
    AFTER UPDATE
    ON __fulltablename__
    FOR EACH ROW
    WHEN (SELECT trigger_update
          FROM trigger_control) = 1
BEGIN
    UPDATE trigger_control SET trigger_update = 0;
    UPDATE __tablename__
    SET changed = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
    UPDATE trigger_control SET trigger_update = 1;
END
';
        $out = '';
        foreach ($tablenames as $tablename) {
            $parts = explode('.', $tablename, 2);
            $dbname = '';
            if (count($parts) === 2) {
                $tablename = $parts[1];
                $dbname = $parts[0] . '.';
            }


            if ($tablename == 'trigger_control') {
                continue;
            }
            $sr = [
              '__tablename__' => $tablename,
              '__fulltablename__' => $dbname . $tablename,
              'set__name__' => $dbname . 'trg_set_updated_at_' . $tablename,
            ];
            try {
                $sqlr = str_replace(array_keys($sr), array_values($sr), $drop);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }
            try {
                $sqlr = str_replace(array_keys($sr), array_values($sr), $create);
                $this->getPdo()->query($sqlr);
                $out .= Core::toLog(__METHOD__, $sqlr);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sqlr);
            }
        }
        return $out;
    }

    public function triggerCreate(array $tablenames): string
    {
        return $this->createUpdate($tablenames);
    }


    public function triggerTableMigrate(bool $versions = false, string $dbname = ''): string
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $dbname . 'trigger_control';
        $sql .= ' (';
        $sql .= 'id               INTEGER PRIMARY KEY,';
        $sql .= 'trigger_update   INTEGER UNIQUE';
        if ($versions) {
            $sql .= ',trigger_versions INTEGER UNIQUE';
        }
        $sql .= ');';

        $sql2 = 'INSERT OR IGNORE INTO ' . $dbname . 'trigger_control(trigger_update';
        if ($versions) {
            $sql2 .= ', trigger_versions';
        }
        $sql2 .= ') VALUES (1';
        if ($versions) {
            $sql2 .= ', 1';
        }
        $sql2 .= ');';
        $this->migrateSql([$sql, $sql2]);

        return $sql . PHP_EOL . $sql2;
    }

    private function createVersionsTable(string $dbname = ''): string
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $dbname . 'versions (
                id       INTEGER PRIMARY KEY,
                table_id  INTEGER, 
                table_name TEXT, 
                column_name TEXT, 
                old_data TEXT, 
                new_data TEXT, 
                user_id TEXT, 
                operation TEXT,
                created  NUMERIC DEFAULT CURRENT_TIMESTAMP
                )';
        $this->migrateSql([$sql]);
        return $sql;
    }

    private function insertTriggerControl(string $col, int $val, string $dbname): void
    {
        // Use named parameter and ON CONFLICT DO NOTHING to avoid duplicate rows
        // Example: INSERT INTO trigger_control(trigger_update) VALUES (:trigger_update) ON CONFLICT(trigger_update) DO NOTHING;
        $col = trim($col, '" ');
        $sql = 'INSERT INTO ' . $dbname . 'trigger_control(' . $col . ') VALUES (:' . $col . ') ON CONFLICT(' . $col . ') DO NOTHING';
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->bindValue(':' . $col, $val, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Erwartet, dass die Klasse diese Methoden bereitstellt
    abstract protected function getPdo(): PDO;

    abstract public function runInsert(string $table, string $colsstring, array $data): string;

    abstract protected function migrateSql(array $queries): string;
}
