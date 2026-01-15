<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\interface\Model;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use cryodrift\fw\Core;


trait DbHelper
{

    public bool $skipexisting = false;

    protected PDO $pdo;

    protected readonly string $connectionstring;

    const string TYPE_BLOBCONTENT = 'contentblob';


    public function connect(string $connectionstring): void
    {
        try {
            $this->connectionstring = $connectionstring;
            Core::dirCreate(Core::pop(explode(':', $connectionstring, 2)));
            $this->pdo = new PDO($connectionstring);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::runQueriesFromFile(__DIR__ . '/dbhelper/p_config.sql');
        } catch (Exception $ex) {
            Core::echo(__METHOD__, $ex->getMessage(), $connectionstring);
        }
    }

    public function getStmt(string $sql): \PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (Exception $ex) {
            Core::echo(__METHOD__, 'ERROR', $ex->getMessage(), $sql, $ex->getTraceAsString());
            throw $ex;
        }
    }

    public function query(string $sql, array $params = [], int $page = 0, int $limit = 0): array
    {
        if ($limit) {
            $sql = rtrim($sql, ';');
            $sql .= self::getPageClause();
        }
        $stmt = $this->getStmt($sql);
        self::bindValues($stmt, array_keys($params), $params);
        if ($limit) {
            self::bindPage($stmt, $page, $limit);
        }
//        Core::echo(__METHOD__,$stmt->queryString);
        $stmt->execute();
        return $stmt->fetchAll();
    }


    public function attachFunction(string $function): void
    {
        $this->pdo->sqliteCreateFunction($function, [$this, $function]);
    }

    /**
     * you need to add escape '\' to your query
     * column like prepareLike($value) escape '/'
     * @param string $value
     * @return string
     */
    public static function prepareLike(string $value): string
    {
        $out = str_replace(['\\', '%'], ['\\\\', '\%'], $value);
        return "%$out%";
    }

    public static function bindValues(PDOStatement $stmt, array $cols, array $data): array
    {
        foreach ($cols as $k => $col) {
            $col = trim($col, '" ');
            $val = Core::getValue($col, $data, null);
            $btype = PDO::PARAM_STR;
            $paramtype = get_debug_type($val);
            switch (true) {
                case is_resource($val) || $col === self::TYPE_BLOBCONTENT:
                    $btype = PDO::PARAM_LOB;
                    break;
                case $paramtype === 'int':
                    $btype = PDO::PARAM_INT;
                    break;
            }
            $stmt->bindValue($k + 1, $val, $btype);
        }
        return $cols;
    }

    public function runInsert(string $table, string|array $columns, array $data): string
    {
        $id = '';
        $sql = '';
        if (count($data)) {
            try {
                $colparts = is_array($columns) ? $columns : explode(',', $columns);
                $colparts = array_map(fn($p) => trim($p, '"'), $colparts);
                $out = [];
                foreach ($data as $key => $value) {
                    if (in_array($key, $colparts)) {
                        $out[$key] = $value;
                    }
                }
                $cols = '"' . implode('","', array_keys($out)) . '"';
                $sql = 'insert into ' . $table . ' (' . $cols . ') values (' . implode(',', array_fill(0, count(explode(',', $cols)), '?')) . ')';
                $stmt = $this->getStmt($sql);
                self::bindValues($stmt, array_keys($out), $data);
                $res = $stmt->execute();
//                Core::echo(__METHOD__,$this->pdo->errorInfo(), $stmt->errorInfo(), Core::removeKeys(['content'], func_get_args()));
                if ($res) {
                    $id = $this->pdo->lastInsertId();
                }
            } catch (PDOException $ex) {
//                Core::echo(__METHOD__, $ex->getCode(), get_debug_type($ex->getCode()), $ex->getCode() === 23000, 'weil', $ex->getMessage(), );
                switch (true) {
                    case str_contains($ex->getMessage(), 'Integrity constraint violation: 19 UNIQUE'):
                        $parts = explode(':', $ex->getMessage());

                        $colnames = explode(Core::getValue(1, $this->splitTablename($table)) . '.', Core::getValue(3, $parts));

                        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

                        $stmt = self::runSelect($table, array_filter($colnames, fn($a) => trim($a)), $data);
                        $res = $stmt->fetch();
                        $id = (string)Core::getValue('id', $res, '', true);
                        if ($id) {
                            if ($this->skipexisting) {
                                return $id;
                            }
                            $rows = self::runUpdate($id, $table, array_filter(explode(',', $cols), fn($a) => trim($a)), $data);
//                            Core::echo(__METHOD__, 'rows',$rows,Core::removeKeys(['content','header_content'],$data));
                        } else {
                            throw new \PDOException(Core::toLog(__METHOD__, 'The select did not work', $sql, $stmt->queryString, $stmt->errorInfo(), Core::removeKeys([], func_get_args()), $ex), 666);
                        }
                        break;
                    default:
                        Core::echo(__METHOD__, 'ERROR', $ex->getCode(), $sql, $ex->getMessage(), $columns, $data);
//                        Core::echo(__METHOD__, $ex->getMessage(), Core::removeKeys(['content'], func_get_args()));
                }
            }

            if (!$id) {
                throw new \PDOException(Core::toLog(__METHOD__, 'Insert failed: ', $sql, Core::removeKeys([], func_get_args())));
            }

            return $id;
        } else {
            //TODO remove this mail_address thingy
            if ($table != 'mail_address') {
                throw new \PDOException(Core::toLog(__METHOD__, 'Insert failed with no data.', Core::removeKeys([], func_get_args())));
            }
        }

        return $id;
    }

    /**
     * @param string $table
     * @param array $cols (a list of columns that have to match in where clause)
     * @param array $data (a unvalidated list of data)
     * @param string $orderby
     * @return PDOStatement
     */
    public function runSelect(string $table, array $cols = [], array $data = [], string $orderby = '', int $page = 0, int $limit = 0): PDOStatement
    {
        $sql = 'select * from ' . $table . ' ';
        $map = [];

        if (count($cols)) {
            $sql .= ' where ';
            foreach ($cols as $col) {
                $col = trim($col, ', ');
                $sql .= ' ' . $col . '=:' . $col . ' and';
                $map[':' . $col] = Core::getValue($col, $data, null);
            }
            $sql = substr($sql, 0, -3);
        }

        if ($orderby) {
            $sql .= ' ' . $orderby;
        }
        if ($limit) {
            $sql .= self::getPageClause();
        }

        $stmt = $this->getStmt($sql);

        if ($limit) {
            $this->bindPage($stmt, $page, $limit);
        }
//        Core::echo(__METHOD__, $sql, $map);
        foreach ($map as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return $stmt;
    }

    public function runUpdate(string $id, string $table, array $cols, array $data): string
    {
        $stmt = $this->getStmt('Select * from ' . $table . ' where id=:id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $olddata = $stmt->fetch();
        $sql = 'update ' . $table . ' set ';
        $map = [];
        $comparedata1 = $comparedata2 = [];
        foreach ($cols as $col) {
            $colvar = trim($col, '" ');
            $sql .= ' ' . $col . '=:' . $colvar . ',';
            $map[$colvar] = Core::getValue($colvar, $data, null);
            $comparedata1[$colvar] = Core::getValue($colvar, $olddata, null);
            $comparedata2[$colvar] = Core::getValue($colvar, $data, null);
        }
        $map['id'] = (int)$id;
//        Core::echo(__METHOD__, 'diff', array_diff_assoc($comparedata1, $comparedata2));
        if (!array_diff_assoc($comparedata1, $comparedata2)) {
            if ($this->skipexisting) {
                return $id;
            } else {
                throw new Exception('Not updated with same data!', 666);
            }
        }

        $sql = rtrim($sql, ',');
        $sql .= ' where id=:id';
        $stmt = $this->getStmt($sql);
        self::bindValues($stmt, array_keys($map), $map);
        try {
            $stmt->execute();
            return (string)$stmt->rowCount();
        } catch (Exception $ex) {
            Core::echo(__METHOD__, $ex);
            if (str_contains($ex->getMessage(), 'Integrity constraint violation: 19 UNIQUE')) {
                throw new Exception('Not updated with same data!', 666);
            } else {
                throw $ex;
            }
        }
    }

    public function runDelete(string $table, string $id): void
    {
        $stmt = $this->getStmt('Delete from ' . $table . ' where id=:id');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }


    public function runQueriesFromFile(string $pathname, string $delim = ';'): string
    {
        $out = '';
        foreach (self::extractQueriesFromFile($pathname, $delim) as $sql) {
            $out .= $sql . PHP_EOL;
            try {
                $this->pdo->exec($sql);
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex, $sql);
            }
        }
        return $out;
    }

    public static function extractQueriesFromFile(string $pathname, string $delim = ';'): iterable
    {
        $file = Core::fileReadOnce($pathname);
        return self::extractQueries($file, $delim);
    }

    public static function extractQueries(string $sqldump, string $delim = ';'): iterable
    {
        foreach (explode($delim, trim($sqldump)) as $sql) {
            if ($sql) {
                $lines = Core::iterate(explode("\n", $sql), function ($line) {
                    $line = trim($line);
                    return str_starts_with($line, '--') ? '' : $line;
                });
                $sql = implode("\n", $lines);
                $sql = $sql . $delim;
                yield $sql;
            }
        }
    }

    public static function getTableParts(string $sql): array
    {
        $sql = trim($sql);
        if (str_starts_with(strtoupper($sql), 'CREATE TABLE')) {
            $tparts = explode('(', $sql, 2);
            $parts = explode(' ', trim($tparts[0]), 6);
//            Core::echo(__METHOD__, $parts,$tparts[1]);
            $nameparts = explode('.', $parts[5]);
            if (count($nameparts) === 2) {
                $tablename = trim($nameparts[0], '"') . '.' . $nameparts[1];
            } else {
                $tablename = $parts[5];
            }

            return [$tablename, trim(trim($tparts[1], ';'), ');')];
        } else {
            return ['', ''];
        }
    }

    public static function escapeForFts($query): string
    {
        // List of characters to escape
        $specialChars = ['.', ',', '"', "'", ';', '-', '_', '/', '\\', '|', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '=', '<', '>', '?', ':'];

        // Escape special characters by adding double quotes around the query
        foreach ($specialChars as $char) {
            $query = str_replace($char, " ", $query);
        }

        return $query;
    }

    public static function bindPage(\PDOStatement $stmt, int $page = 0, int $limit = 20): void
    {
        $offset = self::getOffset($page, $limit);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    }

    public static function getOffset(int $page = 0, int $limit = 20): int
    {
        return $page * $limit;
    }


    public function transaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function vacuum(): void
    {
        $this->pdo->exec('VACUUM;');
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public static function cleanString(string $data): string
    {
        return preg_replace('/[^\w\s\-\_]/', ' ', $data);
    }

    public static function getPageClause(): string
    {
        return ' LIMIT :limit OFFSET :offset';
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function disconnect(): void
    {
        unset($this->pdo);
    }

    public function splitTablename(string $tablename): array
    {
        $parts = explode('.', $tablename, 2);
        if (count($parts) === 2) {
            return [$parts[0] . '.', $parts[1], $parts[0] . '_', $parts[1]];
        } else {
            return ['', $tablename, $tablename];
        }
    }

    public function toModel(string $tablename, array $data = []): Model
    {
        $model = new class implements Model {
            protected string $tablename;
            protected array $fields = [];
            protected array $ignore = ['created', 'changed'];
            protected string $id = 'id';

            public function setId(string $name): Model
            {
                $this->id = $name;
                return $this;
            }

            public function setTable(string $name): Model
            {
                $this->tablename = $name;
                return $this;
            }

            public function setFields(array $data): Model
            {
                $this->fields = $data;
                return $this;
            }

            public function setIgnore(array $ignore, bool $replace = false): Model
            {
                if ($replace) {
                    $this->ignore = $ignore;
                } else {
                    $this->ignore = array_merge($this->ignore, $ignore);
                }
                return $this;
            }

            public function getTable(): string
            {
                return $this->tablename;
            }

            public function getFields(array $ignore = []): array
            {
                $out = $this->fields;
                $ignore = array_merge($ignore, $this->ignore);
                foreach ($ignore as $field) {
                    if (array_key_exists($field, $out)) {
                        unset($out[$field]);
                    }
                }
                return $out;
            }

            public function getColumns(array $ignore = []): array
            {
                $out = $this->fields;
                $ignore = array_merge($ignore, $this->ignore);
                foreach ($ignore as $field) {
                    if (array_key_exists($field, $out)) {
                        unset($out[$field]);
                    }
                }
                return array_keys($out);
            }

            public function getId(string $name = ''): string
            {
                return $this->fields[$name ?: $this->id];
            }

        };
        $model->setTable($tablename);
        $model->setFields($data);
        return $model;
    }

    public function saveModel(Model $mdl): string
    {
        $id = $mdl->getId();
        try {
            $this->runUpdate($id, $mdl->getTable(), $mdl->getColumns(), $mdl->getFields());
        } catch (Exception $ex) {
            Core::echo(__METHOD__, $ex->getMessage());
            $id = $this->runInsert($mdl->getTable(), $mdl->getColumns(), $mdl->getFields());
        }
        return $id;
    }


}
