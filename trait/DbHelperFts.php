<?php

//declare(strict_types=1);

namespace cryodrift\fw\trait;

use cryodrift\fw\Core;

trait DbHelperFts
{
    use DbHelper;

    private array $ftscolumns;
    protected string $ftsquery;
    protected string $ftstablename;
    protected string $ftsdbname;
    // need this for external deletion of db
    public readonly string $ftsdbfile;


    protected function ftsSetup(string $storagedir, string $ftstablename, string $ftsdbname, array $columns): void
    {
        $this->ftsdbfile = $storagedir . $ftsdbname . '.sqlite';
        $this->ftsdbname = $ftsdbname;
        $this->ftstablename = $ftstablename;
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }
        $this->ftscolumns = $columns;
        $this->ftsquery = "ATTACH DATABASE '" . $this->ftsdbfile . "' AS " . $this->ftsdbname;
    }

    protected function ftsAttach(): void
    {
        try {
            $this->query($this->ftsquery);
        } catch (\PDOException $ex) {
            // ignore alread attached database
            $msg = $ex->getMessage();
            if (!str_contains($msg, $this->ftsdbname . ' is already in use')) {
                Core::echo(__METHOD__, $msg, $this->ftsquery);
            }
        }
    }

    private function ftsCreate(): string
    {
        $this->ftsAttach();
        $sql = "CREATE VIRTUAL TABLE IF NOT EXISTS " . $this->ftsdbname . "." . $this->ftstablename . " USING fts5(
            " . implode(',', $this->ftscolumns) . ", content_rowid='id', tokenize='trigram')";
        $this->query($sql);
        return $sql;
    }

    public function ftsRecreate(): string
    {
        $out = [];
        $this->ftsAttach();
        $sql = "drop table IF EXISTS " . $this->ftsdbname . "." . $this->ftstablename;
        $this->query($sql);
        $out[] = $sql;
        $out[] = $this->ftsCreate();
        $this->transaction();
        $out[] = $this->ftsPopulate();
        $this->commit();
        return Core::toLog($out);
    }

    protected function ftsPopulate(): string
    {
        $this->ftsAttach();
        $sql = "INSERT INTO " . $this->ftsdbname . "." . $this->ftstablename . " (" . implode(',', $this->ftscolumns) . ")
                SELECT " . implode(',', $this->ftscolumns) . " FROM " . $this->ftstablename . "";
        $this->query($sql);
        return $sql;
    }

    public function ftsSave(array $data): void
    {
        $this->ftsAttach();
        $id = Core::getValue('id', $data);
        if ($id) {
            $found = $this->ftsGetEntry($id);
//            Core::echo(__METHOD__, 'id', $id, 'found', $found);
            if ($found) {
                Core::echo(__METHOD__, 'update');
                $this->runUpdate(Core::getValue('id', $found), $this->ftsdbname . "." . $this->ftstablename, $this->ftscolumns, $data);
            } else {
                Core::echo(__METHOD__, 'insert');
                $this->runInsert($this->ftsdbname . "." . $this->ftstablename, $this->ftscolumns, $data);
            }
        }
    }

    public function ftsGetEntry(string $id): array
    {
        return Core::pop($this->query("select id from " . $this->ftsdbname . "." . $this->ftstablename . " where cast(id as text)=:id", ['id' => $id]));
    }

    public function ftsSearch(string $query, int $page = 0, int $limit = 0): array
    {
        $data = $this->query("select id from " . $this->ftsdbname . "." . $this->ftstablename . " where " . $this->ftstablename . " match :query", ['query' => $query], $page, $limit);
//        Core::echo(__METHOD__, $data);
        return Core::iterate($data, fn($v) => $v['id'], false, false);
    }


    /**
     * Escape für SQLite-FTS (ganzen String als wörtlichen Ausdruck suchen)
     * - trimmt
     * - Unicode-Whitespace → einfache Leerzeichen
     * - leert leere Ergebnisse
     * - escaped doppelte Anführungszeichen
     * - quoted den ganzen String: "…"
     */
    public function ftsEscapeLiteral(string $input): string
    {
        if ($input === '') {
            return '';
        }
        // Unicode-Whitespace auf einfache Spaces
        $input = preg_replace('/\s+/u', ' ', $input ?? '');
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        $input = str_replace('"', '""', $input);
        return '"' . $input . '"';
    }


}
