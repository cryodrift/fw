<?php

//declare(strict_types=1);

namespace cryodrift\fw\interface;

interface DbHelper
{
    public function connect(string $connectionstring): void;

    public function getStmt(string $sql): \PDOStatement;

    public function query(string $sql, array $params = []): array;

    public function attachFunction(string $function): void;

    public static function prepareLike(string $value): string;

    public static function bindValues(\PDOStatement $stmt, array $cols, array $data): array;

    public function runInsert(string $table, string|array $columns, array $data): string;

    public function runSelect(string $table, array $cols = [], array $data = [], string $orderby = '', int $page = 0, int $limit = 0): \PDOStatement;

    public function runUpdate(string $id, string $table, array $cols, array $data): string;

    public function runDelete(string $table, string $id): void;

    public function runQueriesFromFile(string $pathname, string $delim = ';'): string;

    public static function extractQueriesFromFile(string $pathname, string $delim = ';'): iterable;

    public static function extractQueries(string $sqldump, string $delim = ';'): iterable;

    public static function getTableParts(string $sql): array;

    public static function escapeForFts($query): string;

    public static function bindPage(\PDOStatement $stmt, int $page = 0, int $limit = 20);

    public static function getOffset(int $page = 0, int $limit = 20): int;

    public function transaction():void;

    public function vacuum():void;

    public function commit():void;

    public function rollback():void;

    public static function cleanString(string $data):string;

    public function getPdo(): \PDO;

    public function disconnect():void;

    public function splitTablename(string $tablename): array;

    public function toModel(string $tablename, array $data = []): Model;

    public function saveModel(Model $mdl): string;
}
