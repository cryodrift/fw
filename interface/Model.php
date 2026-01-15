<?php

namespace cryodrift\fw\interface;

interface Model
{
    public function setTable(string $name): Model;

    public function setFields(array $data): Model;

    public function setIgnore(array $ignore, bool $replace = false): Model;

    public function getTable(): string;

    public function getFields(array $ignore = []): array;

    public function getColumns(array $ignore = []): array;

    public function getId(string $name = 'id'): string;

    public function setId(string $name): Model;

}
